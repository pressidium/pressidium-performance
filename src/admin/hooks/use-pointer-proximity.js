import { useState, useEffect } from '@wordpress/element';

/**
 * How close the pointer is to the given element: `over`, `near` or `far`.
 *
 * Distance is measured to the element's box, so it is `0` while the pointer is
 * over the element itself. Pointer moves are throttled to one frame.
 */
// eslint-disable-next-line import/prefer-default-export
export const usePointerProximity = (ref, options = {}) => {
  const { radius = 200, enabled = true } = options;

  const [proximity, setProximity] = useState('far');

  useEffect(() => {
    if (!enabled) {
      // Nothing to track, bail early
      return undefined;
    }

    let frameId = null;
    let pointer = null;

    const update = () => {
      frameId = null;

      const element = ref.current;

      if (!element || !pointer) {
        // Nothing to measure against
        return;
      }

      const rect = element.getBoundingClientRect();

      const distanceX = Math.max(rect.left - pointer.x, 0, pointer.x - rect.right);
      const distanceY = Math.max(rect.top - pointer.y, 0, pointer.y - rect.bottom);
      const distance = Math.sqrt((distanceX * distanceX) + (distanceY * distanceY));

      let next = 'far';

      if (distance === 0) {
        next = 'over';
      } else if (distance <= radius) {
        next = 'near';
      }

      // Bail out of the re-render unless the pointer crossed into another step
      setProximity((current) => (current === next ? current : next));
    };

    const onPointerMove = (event) => {
      pointer = { x: event.clientX, y: event.clientY };

      if (frameId === null) {
        frameId = window.requestAnimationFrame(update);
      }
    };

    const onPointerLeave = () => {
      pointer = null;
      setProximity('far');
    };

    window.addEventListener('pointermove', onPointerMove, { passive: true });
    document.addEventListener('pointerleave', onPointerLeave);

    return () => {
      if (frameId !== null) {
        window.cancelAnimationFrame(frameId);
      }

      window.removeEventListener('pointermove', onPointerMove);
      document.removeEventListener('pointerleave', onPointerLeave);
    };
  }, [ref, radius, enabled]);

  return proximity;
};
