import {
  useState,
  useCallback,
  useEffect,
  createInterpolateElement,
} from '@wordpress/element';
import {
  Flex,
  FlexItem,
  Panel,
  PanelBody,
  PanelRow,
  Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
  page as PageIcon,
  globe as GlobeIcon,
  link as LinkIcon,
  shield as ShieldIcon,
  help as HelpIcon,
  tool as ToolIcon,
  bug as BugIcon,
  dashboard as DashboardIcon,
  category as CategoryIcon,
  pages as PagesIcon,
} from '@wordpress/icons';

import styled from 'styled-components';

import EdgeConnect from 'components/edge/EdgeConnect';
import CacheControl from 'components/edge/CacheControl';
import LinkList, { LinkListItem } from 'components/LinkList';
import FeatureList, { FeatureListItem } from 'components/FeatureList';

const StyledDocsSectionSubtitle = styled.p`
  color: #757575;
`;

function NonPressidiumTab(props) {
  const { appendNotice } = props;

  const {
    edge_hero: edgeHero,
    edge_logo: edgeLogo,
    pressidium_edge_logo: pressidiumEdgeLogo,
  } = pressidiumPerfAdminDetails.assets;
  const [hasCredentials, setHasCredentials] = useState(null);

  const fetchCredentials = useCallback(async () => {
    const { edge_credentials_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: `${route}?nonce=${nonce}`,
      method: 'GET',
    };

    const response = await apiFetch(options);

    if (!('success' in response) || !response.success || !('has_credentials' in response)) {
      // Failed to fetch settings, bail early
      // eslint-disable-next-line no-console
      console.error('Error fetching Pressidium EDGE credentials', response);
      throw new Error('Invalid response while fetching Pressidium EDGE credentials');
    }

    setHasCredentials(response.has_credentials);
  }, []);

  useEffect(() => {
    (async () => {
      await fetchCredentials();
    })();
  }, []);

  if (hasCredentials === null) {
    return (
      <Panel>
        <PanelBody initialOpen>
          <PanelRow>
            <Flex justify="flex-start">
              <FlexItem>
                <Spinner />
              </FlexItem>
              <FlexItem>
                <p>
                  {__('Just a sec, we’re setting things up!', 'pressidium-performance')}
                </p>
              </FlexItem>
            </Flex>
          </PanelRow>
        </PanelBody>
      </Panel>
    );
  }

  return (
    <Panel>
      <div className="pressidium-edge-hero">
        <div className="pressidium-edge-hero__wrapper">
          <div className="pressidium-edge-hero__body">
            <div className="pressidium-edge-hero__image">
              <img
                src={edgeHero}
                alt={__('Pressidium EDGE', 'pressidium-performance')}
              />
            </div>
            <div className="pressidium-edge-hero__text">
              <h2 className="pressidium-edge-hero__heading">
                <span className="pressidium-edge-hero__heading-text">
                  {hasCredentials
                    ? __('Get the most out of', 'pressidium-performance')
                    : __('Keep your host. Gain our', 'pressidium-performance')}
                </span>
                <img
                  src={edgeLogo}
                  alt={__('EDGE', 'pressidium-performance')}
                  className="pressidium-edge-hero__logo"
                />
              </h2>
              <p className="pressidium-edge-hero__subtitle">
                {__('The Ultimate Performance and Security Solution.', 'pressidium-performance')}
              </p>
              <p className="pressidium-edge-hero__description">
                {hasCredentials
                  ? __('From caching to security rules, our documentation shows you exactly how to configure EDGE for peak performance.', 'pressidium-performance')
                  : createInterpolateElement(
                    __('Pressidium EDGE brings <strong>unmatched speed, security, and reliability</strong> to WordPress at global scale. Crush latency, stop threats before they reach you, and scale without limits. Built for those who demand more from their hosting.', 'pressidium-performance'),
                    { strong: <strong /> },
                  )}
              </p>
            </div>
            <img
              src={pressidiumEdgeLogo}
              alt="Pressidium EDGE"
              aria-hidden="true"
              className="pressidium-edge-hero__watermark"
            />
          </div>
          <div className="pressidium-edge-hero__status">
            <EdgeConnect
              hasCredentials={hasCredentials}
              setHasCredentials={setHasCredentials}
              appendNotice={appendNotice}
            />
          </div>
        </div>
      </div>

      {hasCredentials ? (
        <PanelBody
          title={__('Caching', 'pressidium-performance')}
          icon={GlobeIcon}
          initialOpen
        >
          <PanelRow>
            <CacheControl appendNotice={appendNotice} />
          </PanelRow>
        </PanelBody>
      ) : null}

      <PanelBody
        title={__('Features', 'pressidium-performance')}
        icon={CategoryIcon}
        initialOpen
      >
        <PanelRow>
          <FeatureList>
            <FeatureListItem
              icon={GlobeIcon}
              title={__('Edge Caching – Lightning-Fast Global Delivery', 'pressidium-performance')}
              description={[
                __('Accelerate global load times (TTFB, LCP, FCP) for both static and dynamic content, drastically enhancing user experience.', 'pressidium-performance'),
                __('Efficient content delivery tailored to geographically dispersed audiences, ensuring consistently fast performance everywhere.', 'pressidium-performance'),
              ]}
              color="#3858e9"
            />
            <FeatureListItem
              icon={ShieldIcon}
              title={__('Edge WAF - Robust, Proactive Security', 'pressidium-performance')}
              description={[
                __('Specialized WordPress Protection with Actively managed rules against OWASP Top 10 vulnerabilities and known WordPress exploits, including virtual patching.', 'pressidium-performance'),
                __('Advanced Customization with Block or redirect traffic using sophisticated custom rules based on headers, country, IP, device, URL paths, and more.', 'pressidium-performance'),
                __('Comprehensive WAF event reports for deeper security insights.', 'pressidium-performance'),
              ]}
              color="#7c3aed"
            />
            <FeatureListItem
              icon={BugIcon}
              title={__('Vulnerability Reporting', 'pressidium-performance')}
              description={__('Automated scans for updates and vulnerabilities to WordPress core, themes, and plugins. Get notified when action is needed.', 'pressidium-performance')}
              color="#16a34a"
            />
            <FeatureListItem
              icon={DashboardIcon}
              title={__('Performance Audits', 'pressidium-performance')}
              description={__('Automated and on-demand site performance audits. Effortlessly track performance scores (Core Web Vitals) over time, get actionable insights for improving your website’s performance.', 'pressidium-performance')}
              color="#f97316"
            />
            <FeatureListItem
              icon={ToolIcon}
              title={__('Easy setup', 'pressidium-performance')}
              description={__('Seamlessly integrate Pressidium EDGE with your existing WordPress setup. No need for time consuming and complex migrations. Sign up for Pressidium EDGE, switch your DNS and enjoy all the benefits of a superior hosting platform.', 'pressidium-performance')}
              color="#0891b2"
            />
          </FeatureList>
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Documentation', 'pressidium-performance')}
        icon={PagesIcon}
        initialOpen
      >
        <PanelRow>
          <LinkList>
            <LinkListItem
              icon={HelpIcon}
              title={__('Get Started', 'pressidium-performance')}
              description={__('Learn what Pressidium EDGE is, how it works, and how to choose the right plan.', 'pressidium-performance')}
              url="https://help.pressidium.com/pressidium-edge-service/get-started/"
              color="#3858e9"
            />
            <LinkListItem
              icon={ToolIcon}
              title={__('Initial setup', 'pressidium-performance')}
              description={__('Step-by-step guide to connect your WordPress site to Pressidium EDGE.', 'pressidium-performance')}
              url="https://help.pressidium.com/pressidium-edge-service/initial-setup-configuration/"
              color="#7c3aed"
            />
            <LinkListItem
              icon={GlobeIcon}
              title={__('DNS & Domains', 'pressidium-performance')}
              description={__('Point your domain to EDGE using your existing DNS provider.', 'pressidium-performance')}
              url="https://help.pressidium.com/pressidium-edge-service/initial-setup-configuration/pointing-your-domain-to-edge/"
              color="#16a34a"
            />
            <LinkListItem
              icon={ShieldIcon}
              title={__('Security', 'pressidium-performance')}
              description={__('Learn more about how Pressidium EDGE helps protect your site.', 'pressidium-performance')}
              url="https://help.pressidium.com/pressidium-edge-service/site-management/site-security/"
              color="#f97316"
            />
            <LinkListItem
              icon={PageIcon}
              title={__('Caching', 'pressidium-performance')}
              description={__('Learn how to invalidate the cache.', 'pressidium-performance')}
              url="https://help.pressidium.com/pressidium-edge-service/wordpress-integration/purge-edge-cache-from-the-performance-plugin/"
              color="#0891b2"
            />
            <LinkListItem
              icon={LinkIcon}
              title={__('WordPress integration', 'pressidium-performance')}
              description={__('Discover how to install and configure the plugin for seamless integration with Pressidium EDGE.', 'pressidium-performance')}
              url="https://help.pressidium.com/pressidium-edge-service/wordpress-integration/install-pressidium-performance-plugin/"
              color="#ec4899"
            />
          </LinkList>
        </PanelRow>
      </PanelBody>
    </Panel>
  );
}

export default NonPressidiumTab;
