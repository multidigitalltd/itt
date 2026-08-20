/*
 * The canvases.
 *
 * Ported from the design prototype's drawing functions rather than rewritten:
 * the particle field, the candle geometry, the flicker constants and the halo
 * timings are the design, not an implementation detail, and re-deriving them
 * would have produced something that merely resembles it.
 *
 * Two things did change on the way in. The world map was drawn with d3 and
 * topojson from a CDN; here the land ships with the theme as plain rings and the
 * Natural Earth projection is thirty lines of arithmetic, so the page loads no
 * third-party script. And the render loop pauses itself — off-screen canvases
 * and hidden tabs stop costing frames.
 */

window.MSLCanvas = (function () {
	'use strict';

	var RAD = Math.PI / 180;
	var TAU = 6.283185307179586;

	/* Zoom steps in the artwork viewer. Four discrete levels, no free zoom:
	   the composition has to read as two candles at level 0. */
	var ZOOMS = [1, 2.8, 5, 8.5];

	var state = {
		count: 0,
		target: 1,
		accent: '#FFB25C',
		artwork: 'candles',
		artZ: 0,
		artPick: null,
		wallPick: null,
		fx: 0.5,
		fy: 0.38,
		still: false
	};

	var cells = null;
	var N = 76;
	var sprites = null;
	var land = null;
	var mapPoints = [];
	var mapDrawn = false;
	var mapCanvas = null;

	var halo = [];
	var haloLast = 0;

	var flares = [];
	var wallSeen = null;
	var wallExtra = 0;

	var wow = null;
	var raf = null;
	var visible = Object.create(null);
	var observer = null;
	var listeners = [];

	/* ------------------------------------------------------------------
	 * Small helpers
	 * --------------------------------------------------------------- */

	function rng(seed) {
		var a = seed >>> 0;
		return function () {
			a |= 0;
			a = (a + 0x6D2B79F5) | 0;
			var t = Math.imul(a ^ (a >>> 15), 1 | a);
			t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
			return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
		};
	}

	function rgba(hex, alpha) {
		var h = hex.replace('#', '');
		return 'rgba(' + parseInt(h.slice(0, 2), 16) + ',' + parseInt(h.slice(2, 4), 16) + ',' + parseInt(h.slice(4, 6), 16) + ',' + alpha + ')';
	}

	function accentRGB() {
		var h = state.accent.replace('#', '');
		return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
	}

	function bezier(p, t) {
		var u = 1 - t;
		return [
			u * u * p.x0 + 2 * u * t * p.cx + t * t * p.x1,
			u * u * p.y0 + 2 * u * t * p.cy + t * t * p.y1
		];
	}

	function roundRect(g, x, y, w, h, r) {
		if (g.roundRect) {
			g.beginPath();
			g.roundRect(x, y, w, h, r);
			return;
		}
		g.beginPath();
		g.moveTo(x + r, y);
		g.arcTo(x + w, y, x + w, y + h, r);
		g.arcTo(x + w, y + h, x, y + h, r);
		g.arcTo(x, y + h, x, y, r);
		g.arcTo(x, y, x + w, y, r);
		g.closePath();
	}

	/* Device-pixel sizing, capped at 2. Above that the artwork costs four times
	   the fill rate for a difference nobody sees. */
	function fit(cv) {
		var dpr = Math.min(2, window.devicePixelRatio || 1);
		var w = cv.clientWidth;
		var h = cv.clientHeight;

		if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(h * dpr)) {
			cv.width = Math.round(w * dpr);
			cv.height = Math.round(h * dpr);
		}

		var g = cv.getContext('2d');
		g.setTransform(dpr, 0, 0, dpr, 0, 0);

		return { g: g, w: w, h: h };
	}

	function canvas(key) {
		return document.querySelector('canvas[data-msl-canvas="' + key + '"]');
	}

	/* ------------------------------------------------------------------
	 * The particle field
	 * --------------------------------------------------------------- */

	/* Which of the artwork's shapes a given normalised point belongs to, and how
	   hot it is there. Heat drives both the sprite chosen and the size, which is
	   what makes the flames read as flames rather than as a uniform dot field. */
	function mask(kind, x, y, r) {
		var cs, i, cx, dx, t, w, d, f, a, step, k;

		if (kind === 'star') {
			var R = 0.36;
			var scx = 0.5;
			var scy = 0.5;
			var band = 0.018;

			var tri = function (up) {
				var s = up ? 1 : -1;
				var v = [[scx, scy - s * R], [scx - R * 0.866, scy + s * R * 0.5], [scx + R * 0.866, scy + s * R * 0.5]];
				var inside = true;
				var md = 9;

				for (var j = 0; j < 3; j++) {
					var pa = v[j];
					var pb = v[(j + 1) % 3];
					var ex = pb[0] - pa[0];
					var ey = pb[1] - pa[1];
					var px = x - pa[0];
					var py = y - pa[1];
					var cr = ((ex * py - ey * px) / Math.hypot(ex, ey)) * s;
					if (cr > 0) { inside = false; }
					md = Math.min(md, Math.abs(cr));
				}

				return inside && md < band;
			};

			if (tri(true) || tri(false)) {
				d = Math.hypot(x - scx, y - scy) / R;
				return { heat: Math.min(1, 0.35 + d * 0.9) };
			}

			return null;
		}

		if (kind === 'light') {
			cx = 0.5;
			var lcy = 0.60;
			d = Math.hypot(x - cx, y - lcy);

			if (d < 0.14) { return { heat: 1 - (d / 0.14) * 0.35 }; }
			if (Math.abs(y - 0.625) < 0.011 && Math.abs(x - cx) < 0.44) { return { heat: 0.45 }; }

			if (y < 0.63 && d > 0.185 && d < 0.36) {
				a = Math.atan2(lcy - y, x - cx);
				step = Math.PI / 12;
				k = Math.round(a / step);
				if (Math.abs(a - k * step) < 0.042) {
					return { heat: 0.75 * (1 - (d - 0.185) / 0.175) };
				}
			}

			if (d > 0.16 && d < 0.40 && r() < 0.10) { return { heat: 0.28 }; }

			return null;
		}

		/* Two Shabbat candles: flame, wick, body, the flare of the stick, base. */
		cs = [0.345, 0.655];

		for (i = 0; i < cs.length; i++) {
			cx = cs[i];
			dx = Math.abs(x - cx);
			t = (y - 0.190) / 0.215;

			if (t > 0 && t <= 1) {
				w = 0.032 * Math.sin(Math.PI * Math.pow(t, 0.72));
				if (dx <= w) { return { heat: 1 - 0.30 * t }; }
			}

			if (dx <= 0.007 && y > 0.405 && y < 0.450) { return { heat: 0.62 }; }
			if (dx <= 0.046 && y >= 0.443 && y <= 0.775) { return { heat: 0.34 }; }
			if (y > 0.775 && y <= 0.865 && dx <= 0.046 + ((y - 0.775) / 0.090) * 0.072) { return { heat: 0.22 }; }
			if (y > 0.865 && y <= 0.895 && dx <= 0.122) { return { heat: 0.18 }; }
		}

		/* A sparse haze around each flame, so the glow has something to sit on. */
		for (i = 0; i < cs.length; i++) {
			cx = cs[i];
			d = Math.hypot(x - cx, (y - 0.28) * 1.05);

			if (d > 0.07 && d < 0.28) {
				f = 1 - (d - 0.07) / 0.21;
				if (r() < 0.32 * Math.pow(f, 1.6) + 0.03) { return { heat: 0.5 * f }; }
			}
		}

		if (y > 0.10 && y < 0.95 && r() < 0.014) { return { heat: 0.12 }; }

		return null;
	}

	function buildArt() {
		var kind = state.artwork || 'candles';
		var r = rng(20260807);
		var out = [];
		var gx, gy, nx, ny, m;

		for (gy = 0; gy < N; gy++) {
			for (gx = 0; gx < N; gx++) {
				nx = (gx + 0.5 + (gy % 2) * 0.5) / N;
				ny = (gy + 0.5) / N;

				if (nx > 1) { continue; }

				m = mask(kind, nx, ny, r);

				if (m) {
					out.push({
						nx: nx,
						ny: ny,
						heat: Math.max(0, Math.min(1, m.heat)),
						ph: r() * TAU,
						jx: (r() - 0.5) * 0.9,
						jy: (r() - 0.5) * 0.9,
						sc: 0.72 + r() * 0.62
					});
				}
			}
		}

		/* Cooler cells light first, so the artwork fills from its edges inward
		   and the flames are the last thing to complete. */
		var r2 = rng(77);
		out.forEach(function (c) { c.pri = (1 - c.heat) * 0.72 + r2() * 0.55; });
		out.sort(function (a, b) { return a.pri - b.pri; });

		cells = out;
	}

	/* Four pre-rendered radial sprites, from the accent colour to near-white.
	   Drawing a gradient per particle per frame is what makes naive particle
	   fields crawl; drawing an image does not. */
	function buildSprites() {
		var c = accentRGB();
		var make = function (cr, cg, cb) {
			var S = 32;
			var cv = document.createElement('canvas');
			cv.width = S;
			cv.height = S;

			var g = cv.getContext('2d');
			var gr = g.createRadialGradient(S / 2, S / 2, 0, S / 2, S / 2, S / 2);
			gr.addColorStop(0, 'rgba(' + cr + ',' + cg + ',' + cb + ',1)');
			gr.addColorStop(0.16, 'rgba(' + cr + ',' + cg + ',' + cb + ',0.95)');
			gr.addColorStop(0.34, 'rgba(' + cr + ',' + cg + ',' + cb + ',0.50)');
			gr.addColorStop(1, 'rgba(' + cr + ',' + cg + ',' + cb + ',0)');
			g.fillStyle = gr;
			g.fillRect(0, 0, S, S);

			return cv;
		};

		sprites = [0, 0.22, 0.5, 0.82].map(function (t) {
			return make(
				Math.round(c[0] + (255 - c[0]) * t),
				Math.round(c[1] + (255 - c[1]) * t),
				Math.round(c[2] + (252 - c[2]) * t)
			);
		});
	}

	function litCount(extra) {
		if (!cells) { return 0; }
		var total = cells.length;
		return Math.max(0, Math.min(total, Math.round(total * (state.count / state.target)) + (extra || 0)));
	}

	/* ------------------------------------------------------------------
	 * The artwork
	 * --------------------------------------------------------------- */

	function drawArt(cv, o) {
		if (!cv || !cells || !sprites) { return; }

		var f = fit(cv);
		var g = f.g;
		var w = f.w;
		var h = f.h;
		var t = o.t;
		var zoom = o.zoom || 1;
		var scale = o.scale || 1;

		g.clearRect(0, 0, w, h);

		var S = Math.min(w, h) * (o.cover || 0.94);
		var ox = (w - S) / 2;
		var oy = (h - S) / 2 + (o.dy || 0) * h;
		var total = cells.length;
		var lit = litCount(o.extra);
		var cell = S / N;
		var accent = state.accent;
		var i, c, px, py, tw, b, sz, bz;

		g.save();

		if (zoom !== 1) {
			var zfx = ox + (o.fx || 0.5) * S;
			var zfy = oy + (o.fy || 0.5) * S;
			g.translate(w / 2, h / 2);
			g.scale(zoom, zoom);
			g.translate(-zfx, -zfy);
		}

		var glow = g.createRadialGradient(
			ox + S * (o.gx || 0.5), oy + S * (o.gy || 0.34), 0,
			ox + S * (o.gx || 0.5), oy + S * (o.gy || 0.34), S * 0.55
		);
		glow.addColorStop(0, rgba(accent, 0.20));
		glow.addColorStop(0.5, rgba(accent, 0.06));
		glow.addColorStop(1, rgba(accent, 0));
		g.fillStyle = glow;
		g.fillRect(ox - S * 0.2, oy - S * 0.2, S * 1.4, S * 1.4);

		/* Additive blending is the whole trick: overlapping candles add light
		   instead of covering each other, which is how a field of dots reads as
		   a single glowing object. */
		g.globalCompositeOperation = 'lighter';

		for (i = 0; i < total; i++) {
			c = cells[i];
			px = ox + c.nx * S + c.jx * cell;
			py = oy + c.ny * S + c.jy * cell;

			if (i < lit) {
				tw = state.still ? 1 : 0.80 + 0.20 * Math.sin(t * 0.0016 + c.ph);
				b = c.heat > 0.85 ? 3 : c.heat > 0.55 ? 2 : c.heat > 0.3 ? 1 : 0;
				sz = cell * (2.0 + c.heat * 1.5) * c.sc * scale;

				if (c.heat > 0.42) {
					bz = sz * 3.1;
					g.globalAlpha = 0.055 * tw;
					g.drawImage(sprites[1], px - bz / 2, py - bz / 2, bz, bz);
				}

				g.globalAlpha = (0.70 + c.heat * 0.30) * tw;
				g.drawImage(sprites[b], px - sz / 2, py - sz / 2, sz, sz);
			} else {
				g.globalAlpha = 1;
				g.fillStyle = 'rgba(255,208,176,0.14)';
				g.beginPath();
				g.arc(px, py, cell * 0.42 * c.sc * scale, 0, TAU);
				g.fill();
			}
		}

		g.globalAlpha = 1;

		if (o.incoming) {
			drawTrail(g, o.incoming, o.incoming.t, cell, scale, S, ox, oy, 5, 0.045, 5.2, 0.62, 0.16);
		}

		if (o.flash > 0) {
			var fx = ox + (o.fx || 0.5) * S;
			var fy = oy + (o.fy || 0.5) * S;
			var rr = S * 0.30 * o.flash;
			var fg = g.createRadialGradient(fx, fy, 0, fx, fy, rr);
			fg.addColorStop(0, 'rgba(255,255,255,' + (0.85 * (1 - o.flash) + 0.15) + ')');
			fg.addColorStop(0.35, rgba(accent, 0.5 * (1 - o.flash * 0.5)));
			fg.addColorStop(1, rgba(accent, 0));
			g.fillStyle = fg;
			g.fillRect(fx - rr, fy - rr, rr * 2, rr * 2);
			g.strokeStyle = 'rgba(255,255,255,' + (0.5 * (1 - o.flash)) + ')';
			g.lineWidth = 1.5;
			g.beginPath();
			g.arc(fx, fy, rr * 0.9, 0, TAU);
			g.stroke();
		}

		g.globalCompositeOperation = 'source-over';
		g.restore();
	}

	/* The comet tail of a candle travelling into the artwork: the same point
	   drawn a few frames behind itself, each copy smaller and fainter. */
	function drawTrail(g, p, progress, cell, scale, S, ox, oy, steps, lag, size, shrink, fade) {
		var k, tt, pos, sz;

		for (k = steps; k >= 0; k--) {
			tt = Math.max(0, Math.min(1, progress - k * lag));
			pos = bezier(p, tt);
			sz = cell * (size - k * shrink) * scale;
			g.globalAlpha = (1 - k * fade) * 0.9;
			g.drawImage(sprites[3], pos[0] * S + ox - sz / 2, pos[1] * S + oy - sz / 2, sz, sz);
		}

		g.globalAlpha = 1;
	}

	function artFrame(w, h) {
		var S = Math.min(w, h) * 0.94;

		return {
			S: S,
			ox: (w - S) / 2,
			oy: (h - S) / 2,
			cell: S / N,
			Z: ZOOMS[state.artZ || 0],
			fx: state.fx,
			fy: state.fy
		};
	}

	/* One candle, drawn small. Used both in the zoomed artwork and on the wall,
	   so a candle looks like the same object in both places. */
	function miniCandle(g, px, py, u, lit, flick, A) {
		var bw = u * 0.52;
		var bh = u * 1.35;
		var by = py + u * 0.72;

		if (lit) {
			var gs = u * 3.2 * flick;
			g.globalCompositeOperation = 'lighter';
			g.globalAlpha = 0.21 * flick;
			g.drawImage(sprites[2], px - gs / 2, by - bh - u * 0.35 - gs / 2, gs, gs);
			g.globalAlpha = 1;
			g.globalCompositeOperation = 'source-over';
		}

		g.fillStyle = lit ? 'rgba(255,242,224,0.94)' : 'rgba(255,236,218,0.13)';
		roundRect(g, px - bw / 2, by - bh, bw, bh, bw * 0.34);
		g.fill();

		g.fillStyle = lit ? 'rgba(' + A[0] + ',' + A[1] + ',' + A[2] + ',0.45)' : 'rgba(255,236,218,0.08)';
		roundRect(g, px - bw * 0.78, by, bw * 1.56, u * 0.12, u * 0.05);
		g.fill();

		if (lit) {
			var fh = u * 0.82 * flick;
			var fy = by - bh;
			var fw = u * 0.26 * flick;

			g.fillStyle = 'rgba(' + A[0] + ',' + Math.min(255, A[1] + 22) + ',' + Math.min(255, A[2] + 40) + ',0.95)';
			g.beginPath();
			g.moveTo(px, fy - fh);
			g.quadraticCurveTo(px + fw, fy - fh * 0.34, px, fy);
			g.quadraticCurveTo(px - fw, fy - fh * 0.34, px, fy - fh);
			g.fill();

			g.fillStyle = 'rgba(255,252,245,0.94)';
			g.beginPath();
			g.moveTo(px, fy - fh * 0.60);
			g.quadraticCurveTo(px + fw * 0.42, fy - fh * 0.20, px, fy - fh * 0.03);
			g.quadraticCurveTo(px - fw * 0.42, fy - fh * 0.20, px, fy - fh * 0.60);
			g.fill();
		} else {
			g.fillStyle = 'rgba(255,236,218,0.18)';
			g.fillRect(px - u * 0.035, by - bh - u * 0.16, u * 0.07, u * 0.16);
		}
	}

	function drawArtView(cv, t) {
		if (!cv || !cells || !sprites) { return; }

		var f = fit(cv);
		var g = f.g;
		var w = f.w;
		var h = f.h;

		g.clearRect(0, 0, w, h);

		var F = artFrame(w, h);
		var A = accentRGB();
		var total = cells.length;
		var lit = litCount(0);
		var detail = F.Z >= 2.2;
		var cxp = F.ox + F.fx * F.S;
		var cyp = F.oy + F.fy * F.S;
		var i, c, px, py, isLit, flick, tw, b, sz;

		var glow = g.createRadialGradient(w / 2, h * 0.42, 0, w / 2, h * 0.42, Math.max(w, h) * 0.7);
		glow.addColorStop(0, rgba(state.accent, 0.09));
		glow.addColorStop(1, rgba(state.accent, 0));
		g.fillStyle = glow;
		g.fillRect(0, 0, w, h);

		g.save();
		g.translate(w / 2, h / 2);
		g.scale(F.Z, F.Z);
		g.translate(-cxp, -cyp);

		/* Cull to the visible rectangle plus a margin. At the deepest zoom this
		   is the difference between drawing six thousand candles and forty. */
		var m = F.cell * 6;
		var vx0 = cxp - w / (2 * F.Z) - m;
		var vx1 = cxp + w / (2 * F.Z) + m;
		var vy0 = cyp - h / (2 * F.Z) - m;
		var vy1 = cyp + h / (2 * F.Z) + m;

		for (i = 0; i < total; i++) {
			c = cells[i];
			px = F.ox + c.nx * F.S + c.jx * F.cell;
			py = F.oy + c.ny * F.S + c.jy * F.cell;

			if (px < vx0 || px > vx1 || py < vy0 || py > vy1) { continue; }

			isLit = i < lit;

			if (detail) {
				flick = state.still ? 0.9 : 0.84 + 0.12 * Math.sin(t * 0.0042 + c.ph * 3) + 0.05 * Math.sin(t * 0.0113 + c.ph);
				miniCandle(g, px, py, F.cell * 1.5, isLit, flick, A);

				if (state.artPick === i) {
					g.strokeStyle = 'rgba(' + A[0] + ',' + A[1] + ',' + A[2] + ',0.9)';
					g.lineWidth = 1.6 / F.Z;
					roundRect(g, px - F.cell * 1.35, py - F.cell * 1.9, F.cell * 2.7, F.cell * 3.6, F.cell * 0.7);
					g.stroke();
				}
			} else if (isLit) {
				tw = state.still ? 1 : 0.80 + 0.20 * Math.sin(t * 0.0016 + c.ph);
				b = c.heat > 0.85 ? 3 : c.heat > 0.55 ? 2 : c.heat > 0.3 ? 1 : 0;
				sz = F.cell * (2.0 + c.heat * 1.5) * c.sc;
				g.globalCompositeOperation = 'lighter';
				g.globalAlpha = (0.70 + c.heat * 0.30) * tw;
				g.drawImage(sprites[b], px - sz / 2, py - sz / 2, sz, sz);
				g.globalAlpha = 1;
				g.globalCompositeOperation = 'source-over';
			} else {
				g.fillStyle = 'rgba(255,208,176,0.14)';
				g.beginPath();
				g.arc(px, py, F.cell * 0.42 * c.sc, 0, TAU);
				g.fill();
			}
		}

		g.restore();
	}

	/* Which candle a click landed on, in artwork coordinates. */
	function artHitIndex(cv, clientX, clientY) {
		if (!cells) { return null; }

		var rc = cv.getBoundingClientRect();
		var F = artFrame(rc.width, rc.height);
		var ax = (clientX - rc.left - rc.width / 2) / F.Z + (F.ox + F.fx * F.S);
		var ay = (clientY - rc.top - rc.height / 2) / F.Z + (F.oy + F.fy * F.S);
		var best = -1;
		var bd = 1e9;
		var i, c, px, py, d;

		for (i = 0; i < cells.length; i++) {
			c = cells[i];
			px = F.ox + c.nx * F.S + c.jx * F.cell;
			py = F.oy + c.ny * F.S + c.jy * F.cell;
			d = (px - ax) * (px - ax) + (py - ay) * (py - ay);

			if (d < bd) { bd = d; best = i; }
		}

		return Math.sqrt(bd) <= F.cell * 2.4 ? best : null;
	}

	/* ------------------------------------------------------------------
	 * The candle wall
	 * --------------------------------------------------------------- */

	function wallGeom(w, h, mini) {
		var cols = mini ? 7 : 20;
		var cw = w / cols;
		var ch = cw * 1.52;
		var rows = Math.ceil(h / ch) + 1;

		return { cols: cols, cw: cw, ch: ch, rows: rows, total: rows * cols };
	}

	function drawWall(cv, t, mini) {
		if (!cv || !sprites) { return; }

		var f = fit(cv);
		var g = f.g;
		var w = f.w;
		var h = f.h;
		var G = wallGeom(w, h, mini);
		var A = accentRGB();
		var lit, i, k, n, idx, baseLit;

		g.clearRect(0, 0, w, h);

		var bg = g.createLinearGradient(0, 0, 0, h);
		bg.addColorStop(0, 'rgba(34,20,14,0)');
		bg.addColorStop(1, 'rgba(52,28,18,0.55)');
		g.fillStyle = bg;
		g.fillRect(0, 0, w, h);

		if (mini) {
			lit = G.total;
		} else {
			/* The wall shows a full screen at any count, so it is drawn at a
			   fixed density and the *delta* since the last frame is what lights
			   up — capped at three per frame so a burst of joins reads as a
			   sequence of candles rather than as a flash. */
			if (wallSeen === null) { wallSeen = state.count; wallExtra = 0; }

			baseLit = Math.round(G.total * 0.74);

			if (state.count > wallSeen) {
				n = Math.min(3, state.count - wallSeen);

				for (k = 0; k < n; k++) {
					idx = baseLit + wallExtra;
					if (idx < G.total) { flares.push({ idx: idx, t0: t }); wallExtra++; }
				}

				wallSeen = state.count;
			}

			if (baseLit + wallExtra >= G.total) { wallExtra = 0; }

			lit = Math.min(G.total, baseLit + wallExtra);
			flares = flares.filter(function (fl) { return t - fl.t0 < 1400; });
		}

		var flick = function (i) {
			if (state.still) { return 0.9; }
			return 0.84 + 0.11 * Math.sin(t * 0.0042 + i * 1.7) + 0.05 * Math.sin(t * 0.0113 + i * 0.6);
		};

		/* A cheap integer hash instead of a stored random per candle: it gives
		   every candle a stable height and offset without an array the size of
		   the wall. */
		var hsh = function (i, salt) {
			return ((Math.imul(i + salt * 131, 2654435761) >>> 9) % 1000) / 1000;
		};

		var geom = function (i) {
			var row = Math.floor(i / G.cols);
			var col = i % G.cols;

			return {
				cx: col * G.cw + G.cw / 2 + (hsh(i, 2) - 0.5) * G.cw * 0.12,
				by: row * G.ch + G.ch * 0.84,
				bh: G.ch * 0.40 * (0.84 + hsh(i, 1) * 0.32)
			};
		};

		/* Two passes: all the glows first in additive mode, then all the wax.
		   Interleaving them would make each candle's glow wash out its
		   neighbour's body. */
		g.globalCompositeOperation = 'lighter';

		for (i = 0; i < lit; i++) {
			var gm = geom(i);
			var fy = gm.by - gm.bh - G.ch * 0.11;
			var fl = flick(i);
			var gs = G.cw * 1.12 * fl;

			g.globalAlpha = 0.34 * fl;
			g.drawImage(sprites[2], gm.cx - gs / 2, fy - gs / 2, gs, gs);

			var gs2 = G.cw * 2.4 * fl;
			g.globalAlpha = 0.07 * fl;
			g.drawImage(sprites[0], gm.cx - gs2 / 2, fy - gs2 / 2, gs2, gs2);
		}

		g.globalAlpha = 1;
		g.globalCompositeOperation = 'source-over';

		for (i = 0; i < G.total; i++) {
			var m = geom(i);
			var isLit = i < lit;
			var bw = G.cw * 0.21;
			var selected = !mini && state.wallPick === i;

			g.fillStyle = isLit ? 'rgba(255,241,222,0.90)' : 'rgba(255,236,218,0.10)';
			roundRect(g, m.cx - bw / 2, m.by - m.bh, bw, m.bh, bw * 0.34);
			g.fill();

			g.fillStyle = isLit ? 'rgba(' + A[0] + ',' + A[1] + ',' + A[2] + ',0.42)' : 'rgba(255,236,218,0.07)';
			roundRect(g, m.cx - bw * 0.88, m.by, bw * 1.76, G.ch * 0.052, G.ch * 0.02);
			g.fill();

			if (isLit) {
				var f2 = flick(i);
				var fh = G.ch * 0.27 * f2;
				var fy2 = m.by - m.bh;
				var fw = G.cw * 0.115 * f2;

				g.fillStyle = 'rgba(' + A[0] + ',' + Math.min(255, A[1] + 22) + ',' + Math.min(255, A[2] + 40) + ',0.95)';
				g.beginPath();
				g.moveTo(m.cx, fy2 - fh);
				g.quadraticCurveTo(m.cx + fw, fy2 - fh * 0.34, m.cx, fy2);
				g.quadraticCurveTo(m.cx - fw, fy2 - fh * 0.34, m.cx, fy2 - fh);
				g.fill();

				g.fillStyle = 'rgba(255,252,244,0.92)';
				g.beginPath();
				g.moveTo(m.cx, fy2 - fh * 0.62);
				g.quadraticCurveTo(m.cx + fw * 0.42, fy2 - fh * 0.22, m.cx, fy2 - fh * 0.04);
				g.quadraticCurveTo(m.cx - fw * 0.42, fy2 - fh * 0.22, m.cx, fy2 - fh * 0.62);
				g.fill();
			} else {
				g.fillStyle = 'rgba(255,236,218,0.16)';
				g.fillRect(m.cx - G.cw * 0.012, m.by - m.bh - G.ch * 0.05, G.cw * 0.024, G.ch * 0.05);
			}

			if (selected) {
				g.strokeStyle = 'rgba(' + A[0] + ',' + A[1] + ',' + A[2] + ',0.85)';
				g.lineWidth = 1.2;
				roundRect(g, m.cx - G.cw * 0.40, m.by - m.bh - G.ch * 0.34, G.cw * 0.80, m.bh + G.ch * 0.44, G.cw * 0.20);
				g.stroke();
			}
		}

		if (!mini) {
			flares.forEach(function (fl) {
				var p = (t - fl.t0) / 1400;
				var gm = geom(fl.idx);
				var fy = gm.by - gm.bh - G.ch * 0.11;

				g.globalCompositeOperation = 'lighter';
				var gs = G.cw * (1.6 + p * 2.2);
				g.globalAlpha = Math.max(0, 0.75 * (1 - p));
				g.drawImage(sprites[3], gm.cx - gs / 2, fy - gs / 2, gs, gs);

				g.globalAlpha = Math.max(0, 0.55 * (1 - p));
				g.strokeStyle = 'rgba(255,250,240,1)';
				g.lineWidth = 1.4;
				g.beginPath();
				g.arc(gm.cx, fy, G.cw * (0.35 + p * 1.5), 0, TAU);
				g.stroke();

				g.globalAlpha = 1;
				g.globalCompositeOperation = 'source-over';
			});
		}
	}

	function wallHitIndex(cv, clientX, clientY) {
		var rc = cv.getBoundingClientRect();
		var G = wallGeom(rc.width, rc.height, false);
		var col = Math.floor((clientX - rc.left) / G.cw);
		var row = Math.floor((clientY - rc.top) / G.ch);

		return row * G.cols + Math.max(0, Math.min(G.cols - 1, col));
	}

	/* ------------------------------------------------------------------
	 * The hero halo
	 * --------------------------------------------------------------- */

	/*
	 * One breathing radial wash, plus a very sparse stream of points drifting
	 * inward. This was tuned down twice during design and the numbers are the
	 * result: it is meant to be felt and not noticed, so resist raising the
	 * spawn rate or the peak alpha.
	 */
	function drawHalo(cv, t) {
		if (!cv) { return; }

		var f = fit(cv);
		var g = f.g;
		var w = f.w;
		var h = f.h;

		g.clearRect(0, 0, w, h);

		var cx = w / 2;
		var cy = h * 0.50;

		if (!state.still && t - haloLast > 760) {
			haloLast = t;
			var a = Math.random() * TAU;
			var R = Math.max(w, h) * 0.70;

			halo.push({
				t0: t,
				dur: 5200 + Math.random() * 2600,
				x0: cx + Math.cos(a) * R,
				y0: cy + Math.sin(a) * R * 0.78,
				r: 1.6 + Math.random() * 1.6,
				sw: (Math.random() - 0.5) * 90
			});
		}

		var pulse = state.still ? 0.5 : 0.5 + 0.5 * Math.sin(t * 0.00042);
		var RG = Math.min(w, h) * 0.72;
		var gr = g.createRadialGradient(cx, cy, 0, cx, cy, RG);
		gr.addColorStop(0, 'rgba(238,146,58,' + (0.045 + 0.018 * pulse) + ')');
		gr.addColorStop(0.45, 'rgba(243,176,86,0.02)');
		gr.addColorStop(1, 'rgba(246,196,120,0)');
		g.fillStyle = gr;
		g.beginPath();
		g.arc(cx, cy, RG, 0, TAU);
		g.fill();

		halo = halo.filter(function (p) {
			var raw = (t - p.t0) / p.dur;

			if (raw >= 1) { return false; }

			/* Clamped deliberately: an unclamped progress value produced NaN
			   fills the first time this ran. */
			var u = Math.max(0, Math.min(1, raw));
			var e = u * u * (3 - 2 * u);
			var x = p.x0 + (cx - p.x0) * e + Math.sin(u * 3.1) * p.sw * (1 - e);
			var y = p.y0 + (cy - p.y0) * e;
			var alpha = Math.max(0, Math.min(1, u * 3) * (1 - Math.pow(u, 1.8)) * 0.42);
			var rr = p.r * (1 - 0.45 * e);

			var gg = g.createRadialGradient(x, y, 0, x, y, rr * 6);
			gg.addColorStop(0, 'rgba(230,120,36,' + (0.42 * alpha) + ')');
			gg.addColorStop(0.3, 'rgba(240,162,74,' + (0.20 * alpha) + ')');
			gg.addColorStop(1, 'rgba(246,196,120,0)');
			g.fillStyle = gg;
			g.beginPath();
			g.arc(x, y, rr * 6, 0, TAU);
			g.fill();

			g.fillStyle = 'rgba(196,86,22,' + (0.45 * alpha) + ')';
			g.beginPath();
			g.arc(x, y, rr * 0.6, 0, TAU);
			g.fill();

			return true;
		});
	}

	/* ------------------------------------------------------------------
	 * The world map
	 * --------------------------------------------------------------- */

	/*
	 * Natural Earth I. The prototype reached for d3-geo and topojson-client from
	 * a CDN to draw this; the projection is a pair of polynomials and the land is
	 * a list of rings, so both now ship with the theme and the page makes no
	 * third-party request.
	 */
	function naturalEarth1(lonDeg, latDeg) {
		var lam = lonDeg * RAD;
		var phi = latDeg * RAD;
		var p2 = phi * phi;
		var p4 = p2 * p2;

		return [
			lam * (0.8707 - 0.131979 * p2 + p4 * (-0.013791 + p4 * (0.003971 * p2 - 0.001529 * p4))),
			phi * (1.007226 + p2 * (0.015085 + p4 * (-0.044475 + 0.028874 * p2 - 0.005916 * p4)))
		];
	}

	/* The equivalent of d3's fitSize, using the bounds precomputed at build
	   time so nothing has to walk seven thousand points on load. */
	function mapProjection(w, h) {
		var b = land.bounds;
		var W = w;
		var H = h * 1.16;
		var k = Math.min(W / (b[2] - b[0]), H / (b[3] - b[1]));
		var tx = (W - k * (b[2] + b[0])) / 2;
		var ty = (H - k * (b[3] + b[1])) / 2 - h * 0.05;

		return function (lon, lat) {
			var p = naturalEarth1(lon, lat);
			return [p[0] * 150 * k + tx, -p[1] * 150 * k + ty];
		};
	}

	function drawMap(cv) {
		if (!cv || !land) { return; }

		var f = fit(cv);
		var g = f.g;
		var w = f.w;
		var h = f.h;
		var project = mapProjection(w, h);
		var i, j, ring, p;

		g.clearRect(0, 0, w, h);

		g.beginPath();

		for (i = 0; i < land.rings.length; i++) {
			ring = land.rings[i];

			for (j = 0; j < ring.length; j++) {
				p = project(ring[j][0], ring[j][1]);
				if (j === 0) { g.moveTo(p[0], p[1]); } else { g.lineTo(p[0], p[1]); }
			}

			g.closePath();
		}

		g.fillStyle = 'rgba(255,238,225,0.05)';
		g.fill();
		g.strokeStyle = 'rgba(255,238,225,0.11)';
		g.lineWidth = 0.5;
		g.stroke();

		g.globalCompositeOperation = 'lighter';

		mapPoints.forEach(function (c) {
			var pt = project(c.lng, c.lat);
			var r = 1.1 + c.weight * 1.1;
			var gr = g.createRadialGradient(pt[0], pt[1], 0, pt[0], pt[1], r * 4);

			gr.addColorStop(0, rgba(state.accent, 0.95));
			gr.addColorStop(0.25, rgba(state.accent, 0.45));
			gr.addColorStop(1, rgba(state.accent, 0));

			g.fillStyle = gr;
			g.beginPath();
			g.arc(pt[0], pt[1], r * 4, 0, TAU);
			g.fill();

			g.fillStyle = 'rgba(255,250,242,0.95)';
			g.beginPath();
			g.arc(pt[0], pt[1], r * 0.45, 0, TAU);
			g.fill();
		});

		g.globalCompositeOperation = 'source-over';
		mapDrawn = true;
	}

	/* ------------------------------------------------------------------
	 * The join sequence
	 * --------------------------------------------------------------- */

	/*
	 * Four seconds, in five beats: the new point is born off-screen, travels in,
	 * lands with a flash, the camera pulls back, and the words arrive. The
	 * caller is told when the counter should roll and when the copy should
	 * appear, so the DOM stays in step with the canvas.
	 */
	function startWow(handlers) {
		var target = { nx: 0.5, ny: 0.4 };

		if (cells && cells.length) {
			var idx = Math.min(cells.length - 1, Math.max(0, Math.round(cells.length * (state.count / state.target))));
			target = { nx: cells[idx].nx, ny: cells[idx].ny };
		}

		wow = {
			t0: null,
			target: target,
			from: [target.nx < 0.5 ? 1.25 : -0.25, 1.15],
			bumped: false,
			shown: false,
			on: handlers || {}
		};
	}

	function stopWow() {
		wow = null;
	}

	function drawWow(cv, t) {
		if (!cv || !wow) { return; }

		if (wow.t0 === null) { wow.t0 = t; }

		/* Reduced motion keeps the sequence — it is the payoff for the whole
		   flow — but compresses it to a fade rather than removing it. */
		var span = state.still ? 0.28 : 1;
		var e = (t - wow.t0) / span;
		var ease = function (x) { return x < 0 ? 0 : x > 1 ? 1 : 1 - Math.pow(1 - x, 3); };
		var zoom = 1 + 2.0 * ease(e / 1100) - 2.0 * ease((e - 2100) / 1300);
		var flash = 0;
		var incoming = null;
		var extra = 0;

		if (e > 500 && e < 1700) {
			var p = Math.min(1, (e - 500) / 1200);
			incoming = {
				t: p,
				x0: wow.from[0],
				y0: wow.from[1],
				cx: (wow.from[0] + wow.target.nx) / 2 + 0.22,
				cy: Math.min(wow.from[1], wow.target.ny) - 0.30,
				x1: wow.target.nx,
				y1: wow.target.ny
			};
		}

		if (e >= 1680 && e < 2400) { flash = Math.min(1, (e - 1680) / 700); }
		if (e >= 1700) { extra = 1; }

		drawArt(cv, {
			t: t,
			zoom: Math.max(1, zoom),
			fx: wow.target.nx,
			fy: wow.target.ny,
			gx: wow.target.nx,
			gy: wow.target.ny,
			flash: flash,
			incoming: incoming,
			extra: extra,
			cover: 0.72
		});

		if (e > 1700 && !wow.bumped) {
			wow.bumped = true;
			if (wow.on.count) { wow.on.count(); }
		}

		if (e > 3000 && !wow.shown) {
			wow.shown = true;
			if (wow.on.text) { wow.on.text(); }
		}
	}

	/* ------------------------------------------------------------------
	 * The loop
	 * --------------------------------------------------------------- */

	/*
	 * One requestAnimationFrame for every canvas on the page, driven by one
	 * clock. Separate loops per canvas drift out of step with each other, and a
	 * flicker that is out of step between the hero and the wall reads as a bug.
	 *
	 * A canvas that is off-screen or in a hidden tab is not drawn at all.
	 */
	function shouldDraw(cv) {
		if (!cv) { return false; }
		if (document.hidden) { return false; }
		if (!cv.isConnected) { return false; }

		var screen = cv.closest('[data-msl-screen-panel]');

		if (screen && screen.hidden) { return false; }

		return visible[cv.dataset.mslCanvas] !== false;
	}

	function each(key, fn) {
		var cv = canvas(key);

		if (shouldDraw(cv)) { fn(cv); }
	}

	function frame(t) {
		raf = window.requestAnimationFrame(frame);
		tick(t);
	}

	function tick(t) {
		if (!cells || !sprites) { return; }

		each('halo', function (cv) { drawHalo(cv, t); });
		each('hero', function (cv) { drawArt(cv, { t: t, cover: 0.86, dy: 0.02 }); });
		each('artmini', function (cv) { drawArt(cv, { t: t, cover: 1.15, dy: 0.02 }); });
		each('card', function (cv) { drawArt(cv, { t: t, cover: 1.55, dy: 0.30, gy: 0.28 }); });
		each('artview', function (cv) { drawArtView(cv, t); });
		each('wall', function (cv) { drawWall(cv, t, false); });
		each('wallpanel', function (cv) { drawWall(cv, t, false); });
		each('wallmini', function (cv) { drawWall(cv, t, true); });
		each('wow', function (cv) { drawWow(cv, t); });

		var map = canvas('map');

		if (map && land && shouldDraw(map) && (mapCanvas !== map || !mapDrawn)) {
			mapCanvas = map;
			drawMap(map);
		}

		listeners.forEach(function (fn) { fn(t); });
	}

	/* A canvas whose box changed has to be re-fitted; the map is the only one
	   that does not redraw every frame, so it is the only one told explicitly. */
	function watchResize() {
		if (typeof window.ResizeObserver !== 'function') { return; }

		var ro = new window.ResizeObserver(function () {
			mapDrawn = false;
		});

		var map = canvas('map');

		if (map) { ro.observe(map); }
	}

	function watchVisibility() {
		if (typeof window.IntersectionObserver !== 'function') { return; }

		observer = new window.IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				visible[entry.target.dataset.mslCanvas] = entry.isIntersecting;
			});
		}, { rootMargin: '120px' });

		/* The full-screen canvases are exempt: they are inside `hidden` panels,
		   so the observer would report them as invisible forever. shouldDraw()
		   already checks whether their panel is open. */
		Array.prototype.forEach.call(document.querySelectorAll('canvas[data-msl-canvas]'), function (cv) {
			if (cv.closest('[data-msl-screen-panel]')) { return; }
			observer.observe(cv);
		});
	}

	function loadMap(url) {
		if (!url) { return; }

		window.fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data || !data.rings) { return; }
				land = data;
				mapDrawn = false;
			})
			.catch(function () { /* No map is better than a broken page. */ });
	}

	/* ------------------------------------------------------------------
	 * Public surface
	 * --------------------------------------------------------------- */

	function setState(patch) {
		var rebuildArt = ('artwork' in patch && patch.artwork !== state.artwork);
		var rebuildSprites = ('accent' in patch && patch.accent !== state.accent);

		Object.keys(patch).forEach(function (key) { state[key] = patch[key]; });

		if (rebuildArt) { buildArt(); }

		if (rebuildSprites) {
			buildSprites();
			mapDrawn = false;
		}
	}

	function init(options) {
		state.target = Math.max(1, options.target || 1);
		state.count = options.count || 0;
		state.accent = options.accent || state.accent;
		state.artwork = options.artwork || state.artwork;
		state.still = !!options.still;
		mapPoints = options.mapPoints || [];

		buildArt();
		buildSprites();
		watchVisibility();
		watchResize();
		loadMap(options.mapData);

		if (raf === null) { raf = window.requestAnimationFrame(frame); }

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) { mapDrawn = false; }
		});
	}

	return {
		init: init,
		setState: setState,
		state: state,
		zooms: ZOOMS,
		cellCount: function () { return cells ? cells.length : 0; },
		litCount: function () { return litCount(0); },
		artHitIndex: artHitIndex,
		wallHitIndex: wallHitIndex,
		wallGeom: wallGeom,
		startWow: startWow,
		stopWow: stopWow,
		resetWall: function () { wallSeen = null; wallExtra = 0; flares = []; },
		onFrame: function (fn) { listeners.push(fn); },
		redrawMap: function () { mapDrawn = false; },
		setMapPoints: function (points) { mapPoints = points || []; mapDrawn = false; }
	};
}());
