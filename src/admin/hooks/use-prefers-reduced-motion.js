import { useState, useEffect } from '@wordpress/element';

const QUERY = '(prefers-reduced-motion: reduce)';

// eslint-disable-next-line import/prefer-default-export
export const usePrefersReducedMotion = () => {
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(
    () => window.matchMedia(QUERY).matches,
  );

  useEffect(() => {
    const mediaQuery = window.matchMedia(QUERY);

    const onChange = (event) => setPrefersReducedMotion(event.matches);

    mediaQuery.addEventListener('change', onChange);

    return () => mediaQuery.removeEventListener('change', onChange);
  }, []);

  return prefersReducedMotion;
};
