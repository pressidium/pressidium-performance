import {
  useState,
  useCallback,
  useEffect,
  useContext,
} from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  Card,
  CardBody,
  Flex,
  FlexItem,
  Icon,
  Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  box as BoxIcon,
  file as FileIcon,
  pages as PagesIcon,
  gallery as GalleryIcon,
} from '@wordpress/icons';

import SettingsContext from 'store/context';

import styled from 'styled-components';

import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

const StyledCard = styled(Card)`
  height: 100%;
  background-size: 100% 100%;
  background-position: 0 0;
  cursor: pointer;
`;

const StyledValue = styled.span`
  font-size: 1.8rem;
  font-weight: 600;
  text-align: center;
  white-space: nowrap;
`;

const StyledLabel = styled.span`
  display: block;
  text-align: center;
  line-height: 0.94rem;
`;

const StyledFineprint = styled.span`
  font-size: 0.8rem;
  color: #999;
`;

function OverviewTab(props) {
  const { setActiveTab } = props;

  const { state } = useContext(SettingsContext);

  const [isFetching, setIsFetching] = useState(false);
  const [stats, setStats] = useState(null);

  const fetchStats = useCallback(async () => {
    const { stats_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: addQueryArgs(route, { nonce }),
      method: 'GET',
    };

    const response = await apiFetch(options);

    if (!('success' in response) || !response.success || !('data' in response)) {
      // Failed to fetch optimization stats, bail early
      // eslint-disable-next-line no-console
      console.error('Failed to fetch optimization stats', response);
      throw new Error('Invalid response while fetching optimization stats');
    }

    return response.data;
  }, []);

  useEffect(() => {
    (async () => {
      setIsFetching(true);

      try {
        setStats(await fetchStats());
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Could not fetch optimization stats', error);
      }

      setIsFetching(false);
    })();
  }, []);

  if (isFetching) {
    return (
      <Panel>
        <PanelBody initialOpen>
          <Flex justify="flex-start">
            <FlexItem>
              <Spinner />
            </FlexItem>
            <FlexItem>
              <p>
                {__('Hang tight, we’re prepping your optimization stats!', 'pressidium-performance')}
              </p>
            </FlexItem>
          </Flex>
        </PanelBody>
      </Panel>
    );
  }

  return (
    <Panel>
      <PanelBody initialOpen>
        {stats === null ? (
          <PanelRow>
            <p>
              {__('Crunching the numbers. Your optimization stats will show up here once we’re done! 🪄', 'pressidium-performance')}
            </p>
          </PanelRow>
        ) : (
          <>
            <PanelRow>
              <p>
                {__('Quickly review how your optimizations are performing.', 'pressidium-performance')}
              </p>
            </PanelRow>
            <PanelRow>
              <Flex direction="column" align="center" gap={4} style={{ width: '100%', maxWidth: '1500px' }}>
                <FlexItem style={{ width: '100%' }}>
                  <Flex align="stretch" wrap>
                    {[
                      {
                        value: !state.minification.minifyJS && !state.minification.minifyJS
                          ? '0'
                          : stats?.minifications?.files_count || '0',
                        label: __('Files minified', 'pressidium-performance'),
                        icon: PagesIcon,
                        tab: 'minification',
                        gradient: 'radial-gradient(110% 60% at 95% -10%, #f0e1e9 0%, #f0e1e900 100%)',
                        pastel: '#bd80a6',
                      },
                      {
                        value: !state.minification.minifyJS && !state.minification.minifyJS
                          ? '0 B'
                          : stats?.minifications?.total_size_saved || '0 B',
                        label: __('Saved by minifying files', 'pressidium-performance'),
                        icon: FileIcon,
                        tab: 'minification',
                        gradient: 'radial-gradient(110% 60% at 95% -10%, #e6e1f0 0%, #e6e1f000 100%)',
                        pastel: '#8980bd',
                      },
                      {
                        value: !state.concatenation.concatenateJS && !state.concatenation.concatenateCSS
                          ? '0'
                          : stats?.concatenations?.files_count || '0',
                        label: __('Files concatenated', 'pressidium-performance'),
                        icon: BoxIcon,
                        tab: 'concatenation',
                        gradient: 'radial-gradient(110% 60% at 95% -10%, #e1eaf0 0%, #e1eaf000 100%)',
                        pastel: '#809bbd',
                      },
                      {
                        value: stats?.image_optimizations?.total_size_saved || '0 B',
                        label: __('Saved by optimizing images', 'pressidium-performance'),
                        icon: GalleryIcon,
                        tab: 'image-optimization',
                        gradient: 'radial-gradient(110% 60% at 95% -10%, #e1f0e9 0%, #e1f0e900 100%)',
                        pastel: '#80bda0',
                      },
                    ].map(({ value, label, icon, gradient, pastel, tab }) => (
                      <FlexItem isBlock style={{ minWidth: '180px' }}>
                        <StyledCard
                          style={{ backgroundImage: gradient }}
                          onClick={() => setActiveTab(tab)}
                        >
                          <CardBody>
                            <Flex direction="column" align="center" gap={6}>
                              <FlexItem>
                                <Icon
                                  icon={icon}
                                  size={40}
                                  style={{ fill: pastel }}
                                />
                              </FlexItem>
                              <FlexItem>
                                <Flex direction="column" align="center" gap={2}>
                                  <FlexItem>
                                    <StyledValue>
                                      {value}
                                    </StyledValue>
                                  </FlexItem>
                                  <FlexItem>
                                    <StyledLabel>
                                      {label}
                                    </StyledLabel>
                                  </FlexItem>
                                </Flex>
                              </FlexItem>
                            </Flex>
                          </CardBody>
                        </StyledCard>
                      </FlexItem>
                    ))}
                  </Flex>
                </FlexItem>
                <FlexItem>
                  <StyledFineprint>
                    {__('Stats update every 10 minutes, so they might be slightly behind.')}
                  </StyledFineprint>
                </FlexItem>
              </Flex>
            </PanelRow>
          </>
        )}
      </PanelBody>
    </Panel>
  );
}

export default OverviewTab;
