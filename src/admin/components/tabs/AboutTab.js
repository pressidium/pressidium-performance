import { createInterpolateElement } from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  Flex,
  FlexItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  info as InfoIcon,
  help as HelpIcon,
  people as PeopleIcon,
} from '@wordpress/icons';

import styled from 'styled-components';

import Emoji from 'components/Emoji';

import {
  pressidium as PressidiumIcon,
} from 'components/icons';

const StyledHeading = styled.h2`
    font-size: 13px;
`;

function AboutTab() {
  const { icon, promo } = pressidiumPerfAdminDetails.assets;

  const emojis = {
    spiralNotepad: <>&#128466;</>,
    fileCabinet: <>&#128452;</>,
    clamp: <>&#128476;</>,
  };

  const urls = {
    support: {
      github: 'https://github.com/pressidium/pressidium-performance/issues/new',
      forum: 'https://wordpress.org/support/plugin/pressidium-performance/',
    },
    pressidium: {
      home: 'https://pressidium.com/?utm_source=ppplugin&utm_medium=about&utm_campaign=wpplugins',
      technology: 'https://pressidium.com/technology/?utm_source=ppplugin&utm_medium=about&utm_campaign=wpplugins',
      dashboard: 'https://pressidium.com/dashboard/?utm_source=ppplugin&utm_medium=about&utm_campaign=wpplugins',
      features: 'https://pressidium.com/features/?utm_source=ppplugin&utm_medium=about&utm_campaign=wpplugins',
      trial: 'https://pressidium.com/free-trial/?utm_source=ppplugin&utm_medium=about&utm_campaign=wpplugins',
    },
  };

  return (
    <Panel>
      <PanelBody
        title={__('About the Pressidium Performance plugin', 'pressidium-performance')}
        icon={InfoIcon}
        initialOpen
      >
        <PanelRow>
          <Flex
            direction="column"
            gap={0}
            style={{ maxWidth: '800px' }}
          >
            <FlexItem>
              <Flex justify="center">
                <FlexItem>
                  <img
                    src={icon}
                    alt={__('Pressidium Performance', 'pressidium-performance')}
                    style={{ width: '80px' }}
                  />
                </FlexItem>
              </Flex>
            </FlexItem>
            <FlexItem>
              <p>
                {__('The Pressidium Performance plugin is designed to supercharge your website’s speed and improve your visitors’ experience by reducing load times and increasing your website’s performance scores. It optimizes JavaScript and CSS by minifying and merging files, while also compressing images in your Media Library ensuring your website runs at top speed without sacrificing quality.', 'pressidium-performance')}
              </p>
            </FlexItem>
            <FlexItem>
              <StyledHeading>
                <Emoji symbol={emojis.spiralNotepad} style={{ marginRight: '0.3em' }} />
                {__('Minify your scripts and stylesheets', 'pressidium-performance')}
              </StyledHeading>
              <p>
                {__('Supercharge your website’s performance by reducing the size of your JavaScript and CSS files, ensuring faster load times.', 'pressidium-performance')}
              </p>
            </FlexItem>
            <FlexItem>
              <StyledHeading>
                <Emoji symbol={emojis.fileCabinet} style={{ marginRight: '0.3em' }} />
                {__('Concatenate JavaScript and CSS files', 'pressidium-performance')}
              </StyledHeading>
              <p>
                {__('Boost your website’s speed by merging multiple JavaScript and CSS files into a single file, minimizing the number of HTTP requests.', 'pressidium-performance')}
              </p>
            </FlexItem>
            <FlexItem>
              <StyledHeading>
                <Emoji symbol={emojis.clamp} style={{ marginRight: '0.3em' }} />
                {__('Optimize your images', 'pressidium-performance')}
              </StyledHeading>
              <p>
                {__('Improve your website’s load times by compressing your Media Library images without compromising their quality and converting them to modern formats like WebP and AVIF.', 'pressidium-performance')}
              </p>
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Credits', 'pressidium-performance')}
        icon={PeopleIcon}
        initialOpen
      >
        <PanelRow>
          <Flex
            direction="column"
            gap={0}
            style={{ maxWidth: '800px' }}
          >
            <FlexItem>
              <p>
                <ul
                  style={{
                    listStyle: 'square',
                    paddingLeft: '1em',
                  }}
                >
                  <li>
                    {
                      createInterpolateElement(
                        __('Developed and maintained by <a>Pressidium®</a>', 'pressidium-performance'),
                        {
                          a: (
                            // eslint-disable-next-line max-len
                            // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                            <a
                              href={urls.pressidium.home}
                              target="_blank"
                              rel="noreferrer noopener"
                            />
                          ),
                        },
                      )
                    }
                  </li>
                </ul>
              </p>
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Support', 'pressidium-performance')}
        icon={HelpIcon}
        initialOpen
      >
        <PanelRow>
          <Flex
            direction="column"
            gap={0}
            style={{ maxWidth: '800px' }}
          >
            <FlexItem>
              <p>
                {__('To ensure that your issue is addressed efficiently, please use one of the following support channels:', 'pressidium-performance')}
              </p>
              <p>
                <ul
                  style={{
                    listStyle: 'square',
                    paddingLeft: '1em',
                  }}
                >
                  <li>
                    {
                      createInterpolateElement(
                        __('WordPress.org support forum: You can open a topic in the <a>Pressidium Performance support forum</a>.', 'pressidium-performance'),
                        {
                          a: (
                            // eslint-disable-next-line max-len
                            // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                            <a
                              href={urls.support.forum}
                              target="_blank"
                              rel="noreferrer noopener"
                            />
                          ),
                        },
                      )
                    }
                  </li>
                  <li>
                    {
                      createInterpolateElement(
                        __('GitHub issue: You can <a>open an issue on the plugin’s GitHub repository</a> to report a bug or request additional features.', 'pressidium-performance'),
                        {
                          a: (
                            // eslint-disable-next-line max-len
                            // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                            <a
                              href={urls.support.github}
                              target="_blank"
                              rel="noreferrer noopener"
                            />
                          ),
                        },
                      )
                    }
                  </li>
                </ul>
              </p>
              <p>
                {__('Using these channels helps us keep track of all issues and benefits other users who might have similar questions.', 'pressidium-performance')}
              </p>
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('About Pressidium', 'pressidium-performance')}
        icon={PressidiumIcon}
        initialOpen
      >
        <PanelRow>
          <Flex
            direction="column"
            gap={0}
            style={{ maxWidth: '800px' }}
          >
            <FlexItem>
              <p>
                {
                  createInterpolateElement(
                    __('Since 2014, <a>Pressidium</a> has been providing the ultimate in high availability, enterprise-class hosting which is trusted by small businesses through to Fortune 500’s.', 'pressidium-performance'),
                    {
                      a: (
                        // eslint-disable-next-line max-len
                        // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                        <a
                          href={urls.pressidium.home}
                          target="_blank"
                          rel="noreferrer noopener"
                        />
                      ),
                    },
                  )
                }
              </p>
              <p>
                {
                  createInterpolateElement(
                    __('<a>Pressidium N-Tier architecture</a> ensures reliability and security, setting new standards in WordPress hosting.', 'pressidium-performance'),
                    {
                      a: (
                        // eslint-disable-next-line max-len
                        // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                        <a
                          href={urls.pressidium.technology}
                          target="_blank"
                          rel="noreferrer noopener"
                        />
                      ),
                    },
                  )
                }
              </p>
              <p>
                {
                  createInterpolateElement(
                    __('The <a>innovative Dashboard</a>, couples with advanced features included in all plans without extra cost, consolidates and streamlines management for agencies, businesses, and individual site owners.', 'pressidium-performance'),
                    {
                      a: (
                        // eslint-disable-next-line max-len
                        // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                        <a
                          href={urls.pressidium.dashboard}
                          target="_blank"
                          rel="noreferrer noopener"
                        />
                      ),
                    },
                  )
                }
              </p>
              <p>
                {__('With a dedicated support team of WordPress experts and DevOps engineers who work tirelessly 24x7x365, your site is in the best hands, allowing you to focus on your content and business goals.', 'pressidium-performance')}
              </p>
            </FlexItem>
            <FlexItem>
              <a
                href={urls.pressidium.trial}
                target="_blank"
                rel="noreferrer noopener"
              >
                <img
                  src={promo}
                  alt={__('Pressidium. Enjoy 14-days of superior WordPress hosting for free! Learn more.', 'pressidium-performance')}
                  style={{ width: '100%', maxWidth: '930px', marginTop: '1em' }}
                />
              </a>
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>
    </Panel>
  );
}

export default AboutTab;
