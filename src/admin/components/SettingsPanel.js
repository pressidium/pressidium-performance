import {
  useState,
  useEffect,
  useContext,
  useCallback,
  useRef,
} from '@wordpress/element';
import {
  TabPanel,
  Flex,
  FlexItem,
  Spinner,
  Panel as WPPanel,
  PanelHeader,
  PanelBody,
  PanelRow,
  Button,
  Notice,
} from '@wordpress/components';
import {
  help as HelpIcon,
  people as PeopleIcon,
  starFilled as StarIcon,
} from '@wordpress/icons'
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import {
  pressidium as PressidiumIcon,
} from './icons';

import { useBeforeunload } from 'react-beforeunload';

import { usePrevious } from 'hooks';

import Panel from 'components/Panel';
import Footer from 'components/Footer';

import OverviewTab from 'components/tabs/OverviewTab';
import MinificationTab from 'components/tabs/MinificationTab';
import ConcatenationTab from 'components/tabs/ConcatenationTab';
import ImageOptimizationTab from 'components/tabs/ImageOptimizationTab';
import ProcessesTab from 'components/tabs/ProcessesTab';
import LogsTab from 'components/tabs/LogsTab';
import AboutTab from 'components/tabs/AboutTab';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

function SettingsPanel() {
  const [isFetching, setIsFetching] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [notices, setNotices] = useState([]);
  const [selectedTab, setSelectedTab] = useState('minification');
  const [processes, setProcesses] = useState([]);

  const { state, dispatch } = useContext(SettingsContext);

  const urls = {
    docs: 'https://github.com/pressidium/pressidium-performance/wiki',
    review: 'https://wordpress.org/support/plugin/pressidium-performance/reviews/#new-post',
    github: 'https://github.com/pressidium/pressidium-performance/blob/master/CONTRIBUTING.md',
    pressidium: 'https://pressidium.com/free-trial/?utm_source=ppplugin&utm_medium=metabox&utm_campaign=wpplugins',
  };

  const appendNotice = useCallback(({ message, status, id = null, allowHTML = false }) => {
    setNotices((prevNotices) => {
      const noticeExists = prevNotices.find((notice) => notice.id === id);

      if (id !== null && noticeExists) {
        // Notice already exists, do not append twice
        return prevNotices;
      }

      return [
        ...prevNotices,
        {
          id: id || prevNotices.length,
          message,
          status,
          allowHTML,
        },
      ];
    });
  }, []);

  const dismissNotice = useCallback((id) => {
    setNotices((prevNotices) => prevNotices.filter((notice) => notice.id !== id));
  }, []);

  const onDismissNotice = useCallback((id) => {
    dismissNotice(id);
  }, []);

  const fetchSettings = async () => {
    const { route } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'GET',
    };

    const response = await apiFetch(options);

    if (!('success' in response) || !response.success || !('data' in response)) {
      // Failed to fetch settings, bail early
      // eslint-disable-next-line no-console
      console.error('Error fetching settings', response);
      throw new Error('Invalid response while fetching settings');
    }

    const { data } = response;

    return data;
  };

  const saveSettings = async (data) => {
    const { route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: {
        settings: data,
        nonce,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Settings saved successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'settings-save-success',
        });
      } else {
        appendNotice({
          message: __('Could not save settings.', 'pressidium-performance'),
          status: 'error',
          id: 'settings-not-save-error',
        });
      }
    } catch (error) {
      if ('code' in error && error.code === 'invalid_nonce') {
        appendNotice({
          message: __('Could not pass security check.', 'pressidium-performance'),
          status: 'error',
          id: 'failed-security-check-error',
        });
      } else {
        appendNotice({
          message: __('Could not save settings.', 'pressidium-performance'),
          status: 'error',
          id: 'settings-not-save-error',
        });
      }
    }

    setHasUnsavedChanges(false);
  };

  const resetSettings = async () => {
    const { route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'DELETE',
      data: {
        nonce,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Settings reset successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'settings-reset-success',
        });
      } else {
        appendNotice({
          message: __('Could not reset settings.', 'pressidium-performance'),
          status: 'error',
          id: 'settings-not-reset-error',
        });
      }
    } catch (error) {
      if ('code' in error && error.code === 'invalid_nonce') {
        appendNotice({
          message: __('Could not pass security check.', 'pressidium-performance'),
          status: 'error',
          id: 'failed-security-check-error',
        });
      } else {
        appendNotice({
          message: __('Could not reset settings.', 'pressidium-performance'),
          status: 'error',
          id: 'settings-not-reset-error',
        });
      }
    }

    try {
      const data = await fetchSettings();

      dispatch({
        type: ActionTypes.SET_SETTINGS,
        payload: data,
      });
    } catch (error) {
      console.error('Could not reload default settings', error);
    }
  };

  const getCurrentTimestamp = () => {
    const date = new Date();

    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');

    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    const seconds = date.getSeconds().toString().padStart(2, '0');

    return `${year}-${month}-${day}_${hours}-${minutes}-${seconds}`;
  };

  const downloadJsonFile = (data) => {
    const blobType = 'text/json;charset=utf-8';
    const blob = new Blob([JSON.stringify(data)], { type: blobType });
    const url = URL.createObjectURL(blob);

    const currentTimestamp = getCurrentTimestamp();
    const filename = `pressidium-performance-settings_${currentTimestamp}.json`;

    const anchor = document.createElement('a');
    anchor.setAttribute('href', url);
    anchor.setAttribute('download', filename);
    anchor.click();
    anchor.remove();

    URL.revokeObjectURL(url);
  };

  const exportSettings = async () => {
    try {
      const data = await fetchSettings();
      downloadJsonFile(data);
    } catch (error) {
      appendNotice({
        message: __('Could not export settings.', 'pressidium-performance'),
        status: 'error',
        id: 'settings-not-exported-error',
      });
    }
  };

  const importSettings = async (files) => {
    try {
      if (files.length === 0) {
        throw new Error(__('No files selected', 'pressidium-performance'));
      }

      const [file] = files;

      const data = await file.text();
      const parsedData = JSON.parse(data);

      await saveSettings(parsedData);

      dispatch({
        type: ActionTypes.SET_SETTINGS,
        payload: parsedData,
      });
    } catch (error) {
      console.error('Could not import settings', error);
      appendNotice({
        message: error.message,
        status: 'error',
      });
    }
  };

  const fetchBackgroundProcesses = async () => {
    const { processes_route: route } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'GET',
    };

    const response = await apiFetch(options);

    if (!('success' in response) || !response.success || !('data' in response)) {
      // Failed to fetch background processes, bail early
      // eslint-disable-next-line no-console
      console.error('Error fetching background processes', response);
      throw new Error('Invalid response while fetching background processes');
    }

    const { data } = response;

    setProcesses(data);
  };

  const pauseBackgroundProcess = async (action) => {
    const { processes_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: `${route}/pause`,
      method: 'POST',
      data: {
        nonce,
        action,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Background process paused successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'background-process-pause-success',
        });
      } else {
        appendNotice({
          message: __('Could not pause background process.', 'pressidium-performance'),
          status: 'error',
          id: 'background-process-not-pause-error',
        });
      }
    } catch (error) {
      if ('code' in error && error.code === 'invalid_nonce') {
        appendNotice({
          message: __('Could not pass security check.', 'pressidium-performance'),
          status: 'error',
          id: 'failed-security-check-error',
        });
      } else {
        appendNotice({
          message: __('Could not pause background process.', 'pressidium-performance'),
          status: 'error',
          id: 'background-process-not-pause-error',
        });
      }
    }

    try {
      await fetchBackgroundProcesses();
    } catch (error) {
      console.error('Could not fetch background processes', error);
    }
  };

  const resumeBackgroundProcess = async (action) => {
    const { processes_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: `${route}/resume`,
      method: 'POST',
      data: {
        nonce,
        action,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Background process resumed successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'background-process-resume-success',
        });
      } else {
        appendNotice({
          message: __('Could not resume background process.', 'pressidium-performance'),
          status: 'error',
          id: 'background-process-not-resume-error',
        });
      }
    } catch (error) {
      if ('code' in error && error.code === 'invalid_nonce') {
        appendNotice({
          message: __('Could not pass security check.', 'pressidium-performance'),
          status: 'error',
          id: 'failed-security-check-error',
        });
      } else {
        appendNotice({
          message: __('Could not resume background process.', 'pressidium-performance'),
          status: 'error',
          id: 'background-process-not-resume-error',
        });
      }
    }

    try {
      const data = await fetchBackgroundProcesses();

      dispatch({
        type: ActionTypes.SET_BACKGROUND_PROCESSES,
        payload: data,
      });
    } catch (error) {
      console.error('Could not fetch background processes', error);
    }
  };

  const cancelBackgroundProcess = async (action) => {
    const { processes_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: `${route}/cancel`,
      method: 'POST',
      data: {
        nonce,
        action,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Background process canceled successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'background-process-cancel-success',
        });
      } else {
        appendNotice({
          message: __('Could not cancel background process.', 'pressidium-performance'),
          status: 'error',
          id: 'background-process-not-cancel-error',
        });
      }
    } catch (error) {
      if ('code' in error && error.code === 'invalid_nonce') {
        appendNotice({
          message: __('Could not pass security check.', 'pressidium-performance'),
          status: 'error',
          id: 'failed-security-check-error',
        });
      } else {
        appendNotice({
          message: __('Could not cancel background process.', 'pressidium-performance'),
          status: 'error',
          id: 'background-process-not-cancel-error',
        });
      }
    }

    try {
      const data = await fetchBackgroundProcesses();

      dispatch({
        type: ActionTypes.SET_BACKGROUND_PROCESSES,
        payload: data,
      });
    } catch (error) {
      console.error('Could not fetch background processes', error);
    }
  };

  const optimizeExistingImages = async () => {
    const { image_convert_all_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: {
        nonce,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Your images are being optimized in the background. This process might take a while. Feel free to keep working as usual.', 'pressidium-performance'),
          status: 'success',
          id: 'image-optimize-success',
        });
      } else {
        appendNotice({
          message: __('Could not optimize image(s).', 'pressidium-performance'),
          status: 'error',
          id: 'image-not-optimize-error',
        });
      }
    } catch (error) {
      if ('code' in error && error.code === 'invalid_nonce') {
        appendNotice({
          message: __('Could not pass security check.', 'pressidium-performance'),
          status: 'error',
          id: 'failed-security-check-error',
        });
      } else {
        appendNotice({
          message: __('Could not optimize image(s).', 'pressidium-performance'),
          status: 'error',
          id: 'image-not-optimize-error',
        });
      }
    }
  };

  useBeforeunload(hasUnsavedChanges ? (e) => {
    /*
     * Some browsers used to display the returned string in
     * the confirmation dialog, enabling the event handle to
     * display a custom message to the user. However, this is
     * deprecated and no longer supported in most browsers.
     */
    const customMessage = __(
      'You have unsaved changes. Are you sure you want to leave?',
      'pressidium-performance',
    );

    e.preventDefault();
    e.returnValue = customMessage;

    return customMessage;
  } : null);

  const prevState = usePrevious(state);

  useEffect(() => {
    if (prevState && !isFetching) {
      setHasUnsavedChanges(true);
    }
  }, [state]);

  const handleConditionalNotice = (shouldShowNotice, noticeProps) => {
    const { id = null } = noticeProps;

    const noticeExists = notices.find(({ id: noticeId }) => noticeId === id);

    if (shouldShowNotice && !noticeExists) {
      appendNotice({
        ...noticeProps,
        status: 'warning',
      });
    } else if (!shouldShowNotice && noticeExists) {
      dismissNotice(id);
    }
  };

  useEffect(() => {
    const shouldShowNotice = !state.imageOptimization.keepOriginalFiles;

    handleConditionalNotice(
      shouldShowNotice,
      {
        id: 'not-retaining-original-images-warning',
        message: __('Heads up! Your current setting will <strong>delete</strong> the original image files after optimization. That saves space, but that action <strong>cannot be undone</strong>. Make sure you have a backup just in case you need the original files later.', 'pressidium-performance'),
        allowHTML: true,
      }
    );
  }, [state.imageOptimization.keepOriginalFiles]);

  useEffect(() => {
    const shouldShowNotice = !pressidiumPerfAdminDetails.has_page_cache;

    handleConditionalNotice(
      shouldShowNotice,
      {
        id: 'not-page-cache-warning',
        message: __('This plugin performs best with page caching enabled. We recommend activating it via your hosting provider or a caching plugin.', 'pressidium-performance'),
      }
    );
  }, [pressidiumPerfAdminDetails.has_page_cache]);

  useEffect(() => {
    (async () => {
      setIsFetching(true);

      try {
        const data = await fetchSettings();

        dispatch({
          type: ActionTypes.SET_SETTINGS,
          payload: data,
        });
      } catch (error) {
        console.error('Could not fetch settings', error);
      }

      setIsFetching(false);
    })();
  }, []);

  const tabPanelRef = useRef(null);

  const setActiveTab = useCallback((tabName) => {
    const root = tabPanelRef.current;

    if (!root) {
      return;
    }

    // Find candidate elements (`Ariakit.Tab` renders `role="tab"` and also has `aria-controls`)
    const candidates = Array.from(
      root.querySelectorAll('[role="tab"], [aria-controls]')
    );

    const match = candidates.find((el) => {
      const ac = el.getAttribute( 'aria-controls' );

      if (ac && ac.endsWith(`-${ tabName }-view`)) {
        return true;
      }

      // Fallback: match by visible text
      return el.textContent?.trim() === tabName;
    });

    if (match) {
      // trigger selection the same way a user would
      match.click();
      match.focus();
    }
  }, []);

  if (isFetching) {
    return (
      <Spinner />
    );
  }

  return (
    <>
      {notices.map(({ message, status, id, allowHTML = false }) => (
        <Notice
          onRemove={() => onDismissNotice(id)}
          status={status}
          __unstableHTML={allowHTML}
        >
          {message}
        </Notice>
      ))}

      <Flex justify="flex-start" align="flex-start">
        <FlexItem style={{ flexGrow: 1 }}>
          <Panel>
            <TabPanel
              ref={tabPanelRef}
              className="performance-settings-panel"
              activeClass="active-tab"
              onSelect={(tabName) => setSelectedTab(tabName)}
              tabs={[
                {
                  name: 'overview',
                  title: __('Overview', 'pressidium-performance'),
                  className: 'tab-overview',
                  Component: OverviewTab,
                },
                {
                  name: 'image-optimization',
                  title: __('Image optimization', 'pressidium-performance'),
                  className: 'tab-image-optimization',
                  Component: ImageOptimizationTab,
                },
                {
                  name: 'minification',
                  title: __('Minification', 'pressidium-performance'),
                  className: 'tab-minification',
                  Component: MinificationTab,
                },
                {
                  name: 'concatenation',
                  title: __('Concatenation', 'pressidium-performance'),
                  className: 'tab-concatenation pressidium-tab-beta',
                  Component: ConcatenationTab,
                },
                {
                  name: 'processes',
                  title: __('Background processes', 'pressidium-performance'),
                  className: 'tab-processes',
                  Component: ProcessesTab,
                },
                {
                  name: 'logs',
                  title: __('Logs', 'pressidium-performance'),
                  className: 'tab-logs',
                  Component: LogsTab,
                },
                {
                  name: 'about',
                  title: __('About', 'pressidium-performance'),
                  className: 'tab-about',
                  Component: AboutTab,
                },
              ]}
            >
              {({ Component }) => {
                const componentPropsMap = {
                  overview: {
                    setActiveTab,
                  },
                  processes: {
                    processes,
                    fetchBackgroundProcesses,
                    pauseBackgroundProcess,
                    resumeBackgroundProcess,
                    cancelBackgroundProcess,
                  },
                  'image-optimization': {
                    optimizeExistingImages,
                  },
                };

                const props = selectedTab in componentPropsMap
                  ? componentPropsMap[selectedTab]
                  : {};

                return (
                  // eslint-disable-next-line react/jsx-props-no-spreading
                  <Component {...props} />
                );
              }}
            </TabPanel>
            <Footer
              save={() => saveSettings(state)}
              hasUnsavedChanges={hasUnsavedChanges}
              exportSettings={exportSettings}
              importSettings={importSettings}
              resetSettings={resetSettings}
            />
          </Panel>
        </FlexItem>
        <FlexItem
          className="pressidium-hide-on-xl"
          style={{ maxWidth: '300px' }}
        >
          <Flex direction="column">
            <FlexItem>
              <WPPanel>
                <PanelHeader>
                  {__('Need help?', 'pressidium-performance')}
                </PanelHeader>
                <PanelBody>
                  <PanelRow>
                    {__('Browse our step-by-step documentation to set up, customize, and make the most of the plugin.', 'pressidium-performance')}
                  </PanelRow>
                  <PanelRow>
                    <Button
                      icon={HelpIcon}
                      href={urls.docs}
                      target="_blank"
                      variant="secondary"
                    >
                      {__('Read Documentation', 'pressidium-performance')}
                    </Button>
                  </PanelRow>
                </PanelBody>
              </WPPanel>
            </FlexItem>
            <FlexItem>
              <WPPanel>
                <PanelHeader>
                  {__('Enjoying the plugin?', 'pressidium-performance')}
                </PanelHeader>
                <PanelBody>
                  <PanelRow>
                    {__('Share the love! Drop a positive review, keep us smiling and help others find their new favorite plugin!', 'pressidium-performance')}
                  </PanelRow>
                  <PanelRow>
                    <Button
                      icon={StarIcon}
                      href={urls.review}
                      target="_blank"
                      variant="secondary"
                    >
                      {__('Leave a Review', 'pressidium-performance')}
                    </Button>
                  </PanelRow>
                </PanelBody>
              </WPPanel>
            </FlexItem>
            <FlexItem>
              <WPPanel>
                <PanelHeader>
                  {__('Shape the future', 'pressidium-performance')}
                </PanelHeader>
                <PanelBody>
                  <PanelRow>
                    {__('Report issues, suggest improvements, or contribute code. Every bit of feedback helps us grow and improve.', 'pressidium-performance')}
                  </PanelRow>
                  <PanelRow>
                    <Button
                      icon={PeopleIcon}
                      href={urls.github}
                      target="_blank"
                      variant="secondary"
                    >
                      {__('Contribute on GitHub', 'pressidium-performance')}
                    </Button>
                  </PanelRow>
                </PanelBody>
              </WPPanel>
            </FlexItem>
            <FlexItem>
              <WPPanel>
                <PanelHeader>
                  {__('Built by Pressidium®', 'pressidium-performance')}
                </PanelHeader>
                <PanelBody>
                  <PanelRow>
                    {__('Managed hosting for WordPress optimized for performance, security, and scalability.', 'pressidium-performance')}
                  </PanelRow>
                  <PanelRow>
                    {__('Go Further. Go Faster. Go EDGE ⚡', 'pressidium-performance')}
                  </PanelRow>
                  <PanelRow>
                    <span style={{ fontWeight: 600 }}>
                      {__('Enjoy 14-days of superior hosting for free!', 'pressidium-performance')}
                    </span>
                  </PanelRow>
                  <PanelRow>
                    <Button
                      icon={PressidiumIcon}
                      href={urls.pressidium}
                      target="_blank"
                      variant="secondary"
                    >
                      {__('Start your Free Trial', 'pressidium-performance')}
                    </Button>
                  </PanelRow>
                </PanelBody>
              </WPPanel>
            </FlexItem>
          </Flex>
        </FlexItem>
      </Flex>
    </>
  )
}

export default SettingsPanel;
