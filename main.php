<?php
/**
 * Plugin Name: WooCommerce Order Confetti Lite
 * Plugin URI: https://github.com/yourusername/woocommerce-order-confetti-lite
 * Description: Lightweight confetti animation displayed on WooCommerce order success pages.
 * Version: 1.0.0
 * Author: Amirreza Shayesteh Far
 * Author URI: https://github.com/amirrezashf
 * License: GPL v2 or later
 * Text Domain: woocommerce-order-confetti-lite
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOCL_Order_Confetti_Lite {

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_confetti' ), 999 );
	}

	public function render_confetti() {

		if ( is_admin() ) {
			return;
		}

		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-received' ) );

		if ( ! $order_id ) {
			return;
		}
		?>

		<style id="wocl-confetti-style">
			#wocl-confetti-canvas{
				position: fixed !important;
				inset: 0 !important;
				width: 100vw !important;
				height: 100vh !important;
				pointer-events: none !important;
				z-index: 999999 !important;
				display: block !important;
			}

			@media (prefers-reduced-motion: reduce){
				#wocl-confetti-canvas{
					display:none !important;
				}
			}
		</style>

		<canvas id="wocl-confetti-canvas" aria-hidden="true"></canvas>

		<script>
		(function(){
			"use strict";

			try{

				var COLORS = [
					"#ff3b30",
					"#ff9f0a",
					"#ffd60a",
					"#34c759",
					"#30b0ff",
					"#af52de",
					"#ff375f",
					"#0a84ff"
				];

				var DURATION = 3000;
				var EXIT_GRAVITY_BOOST = 0.25;
				var FADE_DECAY = 0.965;
				var MAX_DPR = 1.5;
				var FPS_CAP = 30;

				var isMobile = Math.max(screen.width, innerWidth) <= 768;
				var POOL_SIZE = isMobile ? 70 : 110;
				var LOW_FPS_FACTOR = 0.7;

				var canvas = document.getElementById("wocl-confetti-canvas");

				if (!canvas) {
					return;
				}

				var ctx = canvas.getContext("2d");

				var dpr = Math.min(
					MAX_DPR,
					Math.max(1, window.devicePixelRatio || 1)
				);

				function resizeCanvas() {
					canvas.width = Math.floor(window.innerWidth * dpr);
					canvas.height = Math.floor(window.innerHeight * dpr);
					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				}

				resizeCanvas();

				window.addEventListener("resize", resizeCanvas, {
					passive: true
				});

				function rand(min, max) {
					return Math.random() * (max - min) + min;
				}

				function pick(arr) {
					return arr[(Math.random() * arr.length) | 0];
				}

				function createParticle(initTop) {

					var z = Math.random();
					var scale = 0.6 + (1 - z) * 0.9;

					var isCircle = Math.random() < 0.25;

					var w = isCircle ? rand(4,7) * scale : rand(6,12) * scale;
					var h = isCircle ? w : rand(10,18) * scale;

					return {
						circle: isCircle,
						x: rand(0, innerWidth),
						y: initTop ? -rand(0, innerHeight * 0.8) : -rand(10,60),
						w: w,
						h: h,
						color: pick(COLORS),
						vx: rand(-0.4, 0.4),
						vy: rand(1.4, 2.4) * (1 + (1 - z)),
						rot: rand(0, Math.PI * 2),
						vr: rand(-0.05, 0.05),
						tilt: rand(0, Math.PI * 2),
						tiltSpeed: rand(0, 0.04) + 0.02,
						z: z,
						life: 0,
						fade: 1
					};
				}

				var particles;
				var startTime;
				var lastFrame;
				var accumulator;
				var frameInterval;
				var fpsCheckTime;
				var fpsCounter;
				var fps;
				var running = false;

				function init() {

					particles = new Array(POOL_SIZE);

					for (var i = 0; i < POOL_SIZE; i++) {
						particles[i] = createParticle(true);
					}

					startTime = performance.now();
					lastFrame = startTime;
					accumulator = 0;

					frameInterval = 1000 / FPS_CAP;

					fpsCheckTime = startTime;
					fpsCounter = 0;
					fps = 60;

					running = true;

					requestAnimationFrame(renderFrame);
				}

				function renderFrame(time) {

					if (!running) {
						return;
					}

					var delta = time - lastFrame;
					lastFrame = time;

					accumulator += delta;

					if (accumulator < frameInterval) {
						requestAnimationFrame(renderFrame);
						return;
					}

					accumulator %= frameInterval;

					fpsCounter++;

					if (time - fpsCheckTime > 500) {
						fps = (fpsCounter * 1000) / (time - fpsCheckTime);
						fpsCounter = 0;
						fpsCheckTime = time;
					}

					var width = innerWidth;
					var height = innerHeight;

					var elapsed = time - startTime;
					var isActive = elapsed <= DURATION;

					var activeCount = fps < 35
						? Math.floor(POOL_SIZE * LOW_FPS_FACTOR)
						: POOL_SIZE;

					ctx.clearRect(0, 0, width, height);

					var wind = Math.sin(time * 0.001) * 0.25;

					var allGone = true;

					for (var i = 0; i < POOL_SIZE; i++) {

						var p = particles[i];

						if (i >= activeCount) {
							p.y = -9999;
							continue;
						}

						p.tilt += p.tiltSpeed;

						p.vx += wind * (0.5 + (1 - p.z)) * 0.02;
						p.vy += 0.18 * (0.8 + (1 - p.z));

						if (!isActive) {
							p.vy += EXIT_GRAVITY_BOOST;
							p.fade *= FADE_DECAY;

							if (p.fade < 0.05) {
								p.fade = 0.05;
							}
						}

						p.x += p.vx + Math.sin(p.tilt) * 0.08;
						p.y += p.vy;

						p.rot += p.vr;
						p.life += 16;

						ctx.save();

						ctx.globalAlpha = p.fade;
						ctx.translate((p.x | 0), (p.y | 0));
						ctx.rotate(p.rot);
						ctx.fillStyle = p.color;

						if (p.circle) {

							ctx.beginPath();
							ctx.arc(
								0,
								0,
								(p.w * 0.5) | 0,
								0,
								Math.PI * 2
							);
							ctx.fill();

						} else {

							var scaleY =
								0.85 + 0.15 * Math.sin(p.life * 0.03);

							ctx.fillRect(
								-(p.w / 2) | 0,
								-((p.h * scaleY) / 2) | 0,
								(p.w) | 0,
								(p.h * scaleY) | 0
							);
						}

						ctx.restore();

						if (p.y - 20 > height) {

							if (isActive) {
								particles[i] = createParticle(false);
								allGone = false;
							}

						} else {

							allGone = false;
						}
					}

					if (!isActive && allGone) {
						teardown();
						return;
					}

					requestAnimationFrame(renderFrame);
				}

				function teardown() {

					running = false;

					try {

						window.removeEventListener(
							"resize",
							resizeCanvas
						);

						var canvasElement = document.getElementById(
							"wocl-confetti-canvas"
						);

						if (
							canvasElement &&
							canvasElement.parentNode
						) {
							canvasElement.parentNode.removeChild(
								canvasElement
							);
						}

						var styleElement = document.getElementById(
							"wocl-confetti-style"
						);

						if (
							styleElement &&
							styleElement.parentNode
						) {
							styleElement.parentNode.removeChild(
								styleElement
							);
						}

					} catch (e) {}
				}

				if (document.readyState === "complete") {

					setTimeout(init, 1000);

				} else {

					window.addEventListener(
						"load",
						function() {
							setTimeout(init, 1000);
						},
						{
							once: true
						}
					);
				}

			} catch (e) {}
		})();
		</script>

		<?php
	}
}

new WOCL_Order_Confetti_Lite();
