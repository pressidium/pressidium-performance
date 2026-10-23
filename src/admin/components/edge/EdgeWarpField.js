import { useRef, useEffect } from '@wordpress/element';
import styled from 'styled-components';

import { usePrefersReducedMotion } from 'hooks';

const STAR_COUNT = 200;
const TINTED_RATIO = 0.15;

const IDLE_SPEED = 0.08;
const WARP_SPEED = 0.8;

const IDLE_TRAIL = 1;
const WARP_TRAIL = 6;

// How quickly the current intensity catches up with the target one, per frame
const EASING = 0.05;

// Drawing at the full device pixel ratio is wasted on 3x displays
const MAX_PIXEL_RATIO = 2;

// Taller than the hero image, to give the streaks more room to stretch out
const FIELD_ASPECT_RATIO = 2.2;

// Vanishing point, as a fraction of the canvas, when there is nothing to center on
const ORIGIN_X = 0.5;
const ORIGIN_Y = 0.46;

// Depth at which a star has passed the viewer and gets recycled
const MIN_Z = 0.02;

// How far outside the canvas a star may travel before it gets recycled
const MARGIN = 64;

const StyledField = styled.div`
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  aspect-ratio: ${FIELD_ASPECT_RATIO};
  overflow: hidden;
  pointer-events: none;
  // Fade the streaks out, so they don't get cut off
  -webkit-mask-image: linear-gradient(to bottom, #000000 82%, transparent 100%);
  mask-image: linear-gradient(to bottom, #000000 82%, transparent 100%);
`;

const StyledCanvas = styled.canvas`
  display: block;
  width: 100%;
  height: 100%;
  mix-blend-mode: screen;
`;

const resetStar = (star, isInitial = false) => {
  star.x = (Math.random() * 2) - 1;
  star.y = (Math.random() * 2) - 1;
  // Spread the initial stars across every depth, so the field starts populated
  star.z = isInitial ? (Math.random() * 0.9) + 0.1 : 1;
  star.tinted = Math.random() < TINTED_RATIO;
};

const createStars = () => {
  const stars = [];

  for (let i = 0; i < STAR_COUNT; i += 1) {
    const star = {};

    resetStar(star, true);
    stars.push(star);
  }

  return stars;
};

function EdgeWarpField(props) {
  const { intensity = 0, originRef = null } = props;

  const canvasRef = useRef(null);
  const intensityRef = useRef(intensity);

  const prefersReducedMotion = usePrefersReducedMotion();

  useEffect(() => {
    // Keep the target in a ref, so changing it never restarts the animation
    intensityRef.current = intensity;
  }, [intensity]);

  useEffect(() => {
    const canvas = canvasRef.current;

    if (prefersReducedMotion || !canvas) {
      // Nothing to animate, bail early
      return undefined;
    }

    const context = canvas.getContext('2d');

    if (!context) {
      // Could not get a drawing context, bail early
      return undefined;
    }

    const styles = window.getComputedStyle(canvas);
    const baseColor = styles.getPropertyValue('--pressidium-edge-text').trim() || '#ffffff';
    const tintColor = styles.getPropertyValue('--pressidium-edge-info').trim() || '#60a5fa';

    const stars = createStars();

    let width = 0;
    let height = 0;
    let originX = 0;
    let originY = 0;
    let frameId = null;
    let lastTime = null;
    let isOnScreen = true;
    let currentIntensity = intensityRef.current;

    const project = (star, z) => ({
      x: originX + ((star.x * width) / 2 / z),
      y: originY + ((star.y * height) / 2 / z),
    });

    const resize = () => {
      const canvasRect = canvas.getBoundingClientRect();

      if (canvasRect.width === 0 || canvasRect.height === 0) {
        // Not laid out (yet), nothing to resize to
        return;
      }

      const ratio = Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO);

      width = canvasRect.width;
      height = canvasRect.height;

      canvas.width = Math.round(width * ratio);
      canvas.height = Math.round(height * ratio);

      // Draw in CSS pixels, regardless of the pixel ratio we back the canvas with
      context.setTransform(ratio, 0, 0, ratio, 0, 0);

      const originElement = originRef ? originRef.current : null;

      if (!originElement) {
        originX = width * ORIGIN_X;
        originY = height * ORIGIN_Y;
        return;
      }

      const originRect = originElement.getBoundingClientRect();

      originX = (originRect.left + (originRect.width / 2)) - canvasRect.left;
      originY = (originRect.top + (originRect.height / 2)) - canvasRect.top;
    };

    const draw = (time) => {
      frameId = window.requestAnimationFrame(draw);

      if (lastTime === null) {
        lastTime = time;
      }

      // Clamp the delta, so returning to a backgrounded tab doesn't jump the field forward
      const delta = Math.min((time - lastTime) / 1000, 0.1);
      lastTime = time;

      currentIntensity += (intensityRef.current - currentIntensity) * EASING;

      const speed = IDLE_SPEED + ((WARP_SPEED - IDLE_SPEED) * currentIntensity);
      const trail = IDLE_TRAIL + ((WARP_TRAIL - IDLE_TRAIL) * currentIntensity);

      context.clearRect(0, 0, width, height);
      context.lineCap = 'round';

      stars.forEach((star) => {
        const travelled = speed * delta;

        star.z -= travelled;

        if (star.z <= MIN_Z) {
          resetStar(star);
          return;
        }

        const to = project(star, star.z);

        const hasLeftCanvas = to.x < -MARGIN
          || to.x > width + MARGIN
          || to.y < -MARGIN
          || to.y > height + MARGIN;

        if (hasLeftCanvas) {
          resetStar(star);
          return;
        }

        const from = project(star, Math.min(star.z + (travelled * trail), 1));

        // The closer a star gets, the brighter and thicker its streak
        const depth = 1 - star.z;

        context.globalAlpha = Math.min(depth, 0.9);
        context.strokeStyle = star.tinted ? tintColor : baseColor;
        context.lineWidth = 0.6 + (depth * 1.6);

        context.beginPath();
        context.moveTo(from.x, from.y);
        context.lineTo(to.x, to.y);
        context.stroke();
      });

      context.globalAlpha = 1;
    };

    const start = () => {
      if (frameId !== null) {
        // Already running, bail early
        return;
      }

      lastTime = null;
      frameId = window.requestAnimationFrame(draw);
    };

    const stop = () => {
      if (frameId === null) {
        // Not running, bail early
        return;
      }

      window.cancelAnimationFrame(frameId);
      frameId = null;
    };

    const sync = () => {
      if (isOnScreen && !document.hidden) {
        start();
        return;
      }

      stop();
    };

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(canvas);

    if (originRef && originRef.current) {
      // Re-measure the vanishing point when whatever we center on gets resized
      resizeObserver.observe(originRef.current);
    }

    const intersectionObserver = new IntersectionObserver(([entry]) => {
      isOnScreen = entry.isIntersecting;
      sync();
    });
    intersectionObserver.observe(canvas);

    document.addEventListener('visibilitychange', sync);

    resize();
    sync();

    return () => {
      stop();
      resizeObserver.disconnect();
      intersectionObserver.disconnect();
      document.removeEventListener('visibilitychange', sync);
    };
  }, [prefersReducedMotion]);

  if (prefersReducedMotion) {
    return null;
  }

  return (
    <StyledField aria-hidden="true">
      <StyledCanvas ref={canvasRef} />
    </StyledField>
  );
}

export default EdgeWarpField;
