import { useState, useEffect, useMemo } from '@wordpress/element';
import {
  Button,
  Flex,
  FlexItem,
  Panel,
  PanelBody,
  PanelRow,
  Spinner,
  Dashicon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import ProcessesTable from 'components/tables/ProcessesTable';

function ProcessesTab(props) {
  const {
    processes = [],
    fetchBackgroundProcesses = () => {},
    pauseBackgroundProcess = () => {},
    resumeBackgroundProcess = () => {},
    cancelBackgroundProcess = () => {},
  } = props;

  const [isFetching, setIsFetching] = useState(false);

  const getStatus = ({
    isCancelled = false,
    isPaused = false,
    isProcessing = false,
    isQueued = false,
  }) => {
    if (isCancelled) {
      return {
        icon: () => <Dashicon icon="warning" />,
        status: __('Cancelled', 'pressidium-performance'),
        initialOpen: true,
      }
    }

    if (isPaused) {
      return {
        icon: () => <Dashicon icon="controls-pause" />,
        status: __('Paused', 'pressidium-performance'),
        initialOpen: true,
      }
    }

    if (isProcessing) {
      return {
        icon: () => <Dashicon icon="controls-play" />,
        status: __('Processing', 'pressidium-performance'),
        initialOpen: true,
      }
    }

    if (isQueued) {
      return {
        icon: () => <Dashicon icon="clock" />,
        status: __('Queued', 'pressidium-performance'),
        initialOpen: true,
      }
    }

    return {
      icon: () => <Dashicon icon="warning" />,
      status: __('Unknown status', 'pressidium-performance'),
      initialOpen: false,
    }
  }

  const updateProcesses = async () => {
    setIsFetching(true);

    try {
      await fetchBackgroundProcesses();
    } catch (error) {
      console.error('Could not fetch background processes', error);
    }

    setIsFetching(false);
  };

  useEffect(() => {
    let interval = null;

    (async () => {
      interval = setInterval(updateProcesses, 10000);
      await updateProcesses();
    })();

    return () => {
      setIsFetching(false);

      if (interval !== null) {
        clearInterval(interval);
      }
    }
  }, []);

  const hasActiveProcesses = useMemo(
    () => processes.filter((process) => process.is_active).length > 0,
    [processes]
  );

  if (!hasActiveProcesses) {
    return (
      <Panel>
        <PanelBody initialOpen>
          {isFetching ? (
            <Flex justify="flex-start">
              <FlexItem>
                <Spinner />
              </FlexItem>
              <FlexItem>
                <p>
                  {__('Just a sec, we’re gathering all your background processes', 'pressidium-performance')}
                </p>
              </FlexItem>
            </Flex>
          ) : (
            <p>
              {__('No processes in queue! All done 🎉', 'pressidium-performance')}
            </p>
          )}
        </PanelBody>
      </Panel>
    );
  }

  return (
    <Panel>
      <PanelBody initialOpen>
        <PanelRow>
          <p>
            {__('Here’s the magic happening behind the scenes 🪄', 'pressidium-performance')}
          </p>
        </PanelRow>
      </PanelBody>
      {processes.map((process) => {
        const {
          action,
          is_cancelled: isCancelled,
          is_paused: isPaused,
          is_processing: isProcessing,
          is_queued: isQueued,
          items,
        } = process;

        const { icon: Icon, status, initialOpen} = getStatus({
          isCancelled,
          isPaused,
          isProcessing,
          isQueued,
        });

        return (
          <PanelBody
            title={`${__('Process ', 'pressidium-performance')} ${process.action}`}
            initialOpen={initialOpen}
          >
            <PanelRow>
              <Flex
                direction="column"
                gap={4}
                style={{width: '100%'}}
              >
                <FlexItem>
                  <Flex
                    direction="column"
                    gap={4}
                    style={{width: '100%'}}
                  >
                    <FlexItem>
                      <Flex justify="flex-start">
                        <FlexItem>
                          <Icon />
                        </FlexItem>
                        <FlexItem>
                          <p>
                            {status}
                          </p>
                        </FlexItem>
                      </Flex>
                    </FlexItem>
                    <FlexItem>
                      <Flex justify="flex-start">
                        <FlexItem>
                          <Button
                            variant="secondary"
                            icon={() => (<Dashicon icon={isPaused ? 'controls-play' : 'controls-pause'} />)}
                            onClick={() => {
                              if (isPaused) {
                                resumeBackgroundProcess(action);
                              } else {
                                pauseBackgroundProcess(action);
                              }
                            }}
                            disabled={isCancelled}
                            style={{paddingRight: '10px'}}
                          >
                            {isPaused
                              ? __('Resume', 'pressidium-performance')
                              : __('Pause', 'pressidium-performance')
                            }
                          </Button>
                        </FlexItem>
                        <FlexItem>
                          <Button
                            variant="secondary"
                            className="is-destructive"
                            icon={() => (<Dashicon icon="trash" />)}
                            onClick={() => cancelBackgroundProcess(action)}
                            disabled={isCancelled}
                            style={{paddingRight: '10px'}}
                          >
                            {__('Cancel', 'pressidium-performance')}
                          </Button>
                        </FlexItem>
                      </Flex>
                    </FlexItem>
                    {!isCancelled && (
                      <FlexItem>
                        <Flex
                          direction="column"
                          gap={0}
                        >
                          <FlexItem>
                            <p>
                              {__('Current batch:', 'pressidium-performance')}
                            </p>
                          </FlexItem>
                          <FlexItem>
                            <ProcessesTable items={items} />
                          </FlexItem>
                        </Flex>
                      </FlexItem>
                    )}
                  </Flex>
                </FlexItem>
                <FlexItem>
                  {isFetching ? (
                    <Spinner />
                  ) : null}
                </FlexItem>
              </Flex>
            </PanelRow>
          </PanelBody>
        );
      })}
    </Panel>
  );
}

export default ProcessesTab;
