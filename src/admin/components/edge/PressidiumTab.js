import { useState, useRef, useCallback } from '@wordpress/element';
import { Button, Panel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { usePointerProximity } from 'hooks';

import EdgeSection from 'components/edge/EdgeSection';
import EdgeWarpField from 'components/edge/EdgeWarpField';
import EdgeFeatureList from 'components/edge/EdgeFeatureList';
import EdgeFaq from 'components/edge/EdgeFaq';

// How close the pointer has to get to the CTA before the warp picks up
const NEAR_RADIUS = 90;

const WARP_INTENSITIES = {
  far: 0,
  near: 0.3,
  over: 1,
};

const EDGE_DOCS_URL = 'https://help.pressidium.com/pressidium-edge-service/';

const CTA_CLASS_NAME = 'pressidium-edge-hero__action pressidium-edge-hero__action--outline';

function PressidiumTab() {
  const { edge_hero_alt: edgeHero, edge_logo: edgeLogo } = pressidiumPerfAdminDetails.assets;
  const {
    is_platform_edge: isPlatformEdge,
    edge_upgrade_url: upgradeUrl,
  } = pressidiumPerfAdminDetails;

  const upgradeButtonRef = useRef(null);

  const [isFocused, setIsFocused] = useState(false);

  const startWarping = useCallback(() => setIsFocused(true), []);
  const stopWarping = useCallback(() => setIsFocused(false), []);

  const proximity = usePointerProximity(upgradeButtonRef, {
    radius: NEAR_RADIUS,
    enabled: !isPlatformEdge,
  });

  // Keyboard users get the full warp as soon as they reach the CTA
  const intensity = isFocused ? 1 : WARP_INTENSITIES[proximity];

  const sections = [
    {
      eyebrow: __('Unmatched Speed', 'pressidium-performance'),
      heading: __('Edge Caching – Lightning-Fast Global Delivery', 'pressidium-performance'),
      features: [
        {
          title: __('Unmatched Speed', 'pressidium-performance'),
          description: __('Get up to 30% performance improvement 🚀 by leveraging our brand new Edge Caching.', 'pressidium-performance'),
        },
        {
          title: __('Static & Dynamic Content Edge Caching', 'pressidium-performance'),
          description: __('Accelerates global load times (TTFB, LCP, FCP) for both static and dynamic content, drastically enhancing user experience.', 'pressidium-performance'),
        },
        {
          title: __('Optimized Global Reach', 'pressidium-performance'),
          description: __('Efficient content delivery (CDN) tailored to geographically dispersed audiences, ensuring consistently fast performance everywhere.', 'pressidium-performance'),
        },
        {
          title: __('Advanced HTTP/3 Protocol', 'pressidium-performance'),
          description: __('Future-proof your website with cutting-edge HTTP/3 support.', 'pressidium-performance'),
        },
      ],
    },
    {
      eyebrow: __('Unbreakable Security', 'pressidium-performance'),
      heading: __('Edge WAF – Robust, Proactive Security', 'pressidium-performance'),
      features: [
        {
          title: __('Edge-Level Attack Mitigation', 'pressidium-performance'),
          description: __('Stops volumetric (L3/L4) and application-layer (L7) threats such as ICMP DDoS, SYN floods, brute-force attacks, and malicious POST requests before they reach your infrastructure.', 'pressidium-performance'),
        },
        {
          title: __('Intelligent - Specialized WordPress Protection', 'pressidium-performance'),
          description: __('Even greater security. All the security features you currently enjoy, such as protection against OWASP Top 10 vulnerabilities, WordPress-specific vulnerabilities, and other known exploits, plus AI-enhanced threat mitigation for even stronger protection.', 'pressidium-performance'),
        },
        {
          title: __('Advanced Customization', 'pressidium-performance'),
          badge: __('Coming Soon', 'pressidium-performance'),
          description: __('Block or redirect traffic using sophisticated custom rules based on headers, country, IP, device, URL paths, and more.', 'pressidium-performance'),
        },
        {
          title: __('Enhanced Reporting', 'pressidium-performance'),
          badge: __('Coming Soon', 'pressidium-performance'),
          description: __('Comprehensive WAF event reports for deeper security insights.', 'pressidium-performance'),
        },
      ],
    },
  ];

  const questions = [
    {
      question: __('Will upgrading affect my current site setup?', 'pressidium-performance'),
      answer: __('No, the upgrade process is seamless and will not affect your current site setup. Our team manages the entire process to ensure zero downtime and preserve all your existing configurations. The only action required on your part is updating your DNS settings once the upgrade is ready.', 'pressidium-performance'),
    },
    {
      question: __('Can I switch back if I don’t like it?', 'pressidium-performance'),
      answer: __('Yes, you can switch back at any time. However, we’re confident you’ll love the performance improvements and enhanced features that come with Pressidium EDGE.', 'pressidium-performance'),
    },
    {
      question: __('Is this free or will I be charged?', 'pressidium-performance'),
      answer: __('The upgrade to Pressidium EDGE is completely FREE. There are no additional charges for accessing our next-generation infrastructure.', 'pressidium-performance'),
    },
    {
      question: __('How long does the upgrade take?', 'pressidium-performance'),
      answer: __('The upgrade typically takes 24 to 48 hours, but usually much less. Our team will coordinate with you to schedule it at a convenient time to avoid any disruptions.', 'pressidium-performance'),
    },
    {
      question: __('What is Edge Caching?', 'pressidium-performance'),
      answer: __('Edge Caching stores both static and dynamic content on distributed global edge nodes closer to your visitors, dramatically improving load times and scalability while processing requests. While traditionally used for static assets (like media files and static assets like CSS & JavaScript), Pressidium EDGE introduces smart caching of dynamic pages (full HTML generated content), like product listings and search results and is specifically designed to work seamlessly with WordPress, including support for popular plugins and e-commerce suites. By using adaptive, intelligent, cache updates and real-time purging it delivers fast, optimized content without sacrificing freshness or accuracy.', 'pressidium-performance'),
    },
  ];

  return (
    <Panel>
      <div className="pressidium-edge-hero is-pressidium">
        <div className="pressidium-edge-hero__wrapper">
          {isPlatformEdge ? (
            <div className="pressidium-edge-hero__status pressidium-edge-hero__status--top">
              <div className="pressidium-edge-hero__status-left">
                <span className="pressidium-edge-hero__status-dot" aria-hidden="true">&#10003;</span>
                <div>
                  <div className="pressidium-edge-hero__status-label">
                    {__('Connected', 'pressidium-performance')}
                  </div>
                  <div className="pressidium-edge-hero__status-sublabel">
                    {__('Your site is connected to Pressidium EDGE.', 'pressidium-performance')}
                  </div>
                </div>
              </div>
            </div>
          ) : null}
          <div className="pressidium-edge-hero__body">
            <div className="pressidium-edge-hero__image">
              <img
                src={edgeHero}
                alt={__('Pressidium EDGE', 'pressidium-performance')}
              />
            </div>
            {!isPlatformEdge ? (
              <EdgeWarpField intensity={intensity} originRef={upgradeButtonRef} />
            ) : null}
            <div className="pressidium-edge-hero__content">
              <EdgeSection
                variant="hero"
                level={2}
                className={isPlatformEdge ? 'pressidium-edge-hero__section--connected' : null}
                eyebrow={(
                  <span className="pressidium-edge-hero__meet">
                    <span className="pressidium-edge-hero__heading-text">
                      {__('Meet', 'pressidium-performance')}
                    </span>
                    <img
                      src={edgeLogo}
                      alt={__('Pressidium EDGE', 'pressidium-performance')}
                      className="pressidium-edge-hero__logo"
                    />
                  </span>
                )}
                heading={isPlatformEdge
                  ? __('Your WordPress Site is Supercharged with our Next-Gen Network', 'pressidium-performance')
                  : __('Supercharge Your WordPress Site with our Next-Gen Network', 'pressidium-performance')}
                description={__('Blazing speed, fortified security, and intelligent scaling — built for modern WordPress.', 'pressidium-performance')}
              >
                <div className="pressidium-edge-hero__actions pressidium-edge-hero__actions--hero">
                  {isPlatformEdge ? (
                    <Button
                      variant="secondary"
                      className={CTA_CLASS_NAME}
                      href={EDGE_DOCS_URL}
                      target="_blank"
                    >
                      {__('Read the docs', 'pressidium-performance')}
                    </Button>
                  ) : (
                    <Button
                      ref={upgradeButtonRef}
                      variant="secondary"
                      className={CTA_CLASS_NAME}
                      href={upgradeUrl}
                      target="_blank"
                      onFocus={startWarping}
                      onBlur={stopWarping}
                    >
                      {__('Upgrade now', 'pressidium-performance')}
                    </Button>
                  )}
                </div>
              </EdgeSection>

              {sections.map(({ eyebrow, heading, features }, index) => (
                <EdgeSection
                  key={heading}
                  eyebrow={eyebrow}
                  heading={heading}
                  className={index === 0 ? 'pressidium-edge-hero__section--first' : null}
                >
                  <EdgeFeatureList features={features} />
                </EdgeSection>
              ))}

              {!isPlatformEdge ? (
                <EdgeSection
                  variant="faq"
                  heading={__('FAQ', 'pressidium-performance')}
                >
                  <EdgeFaq questions={questions} />
                </EdgeSection>
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </Panel>
  );
}

export default PressidiumTab;
