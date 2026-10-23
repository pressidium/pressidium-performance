import { useContext, useCallback, useState } from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  ToggleControl,
  Button,
  Flex,
  FlexItem,
} from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import {
  settings as SettingsIcon,
  brush as BrushIcon,
  code as CodeIcon,
  archive as ArchiveIcon,
  tool as ToolIcon,
} from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import MinificationExclusionsTable from 'components/tables/exclusion/MinificationExclusionsTable';
import MinificationsTable from 'components/tables/MinificationsTable';
import ClearMinifiedFilesModal from 'components/modals/ClearMinifiedFilesModal';

function MinificationTab(props) {
  const { appendNotice } = props;

  const { state, dispatch } = useContext(SettingsContext);

  const [isClearing, setIsClearing] = useState(false);
  const [isClearModalOpen, setIsClearModalOpen] = useState(false);

  /*
   * Bumped after a successful clear. It's used as the `key` of the minifications
   * table, so that bumping it remounts the table and resets its internal state.
   */
  const [refreshToken, setRefreshToken] = useState(0);

  const onMinificationSettingChange = useCallback((key, value) => {
    dispatch({
      type: ActionTypes.SET_MINIFICATION_SETTING,
      payload: {
        key,
        value,
      },
    });
  }, []);

  const openClearModal = useCallback(() => setIsClearModalOpen(true), []);
  const closeClearModal = useCallback(() => setIsClearModalOpen(false), []);

  const clearMinifiedFiles = useCallback(async () => {
    setIsClearing(true);

    const { minification_clear_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: { nonce },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        const filesDeleted = response.data?.files_deleted ?? 0;

        if (filesDeleted === 0) {
          appendNotice({
            message: __('No minified files were found to clear.', 'pressidium-performance'),
            status: 'info',
            id: 'minification-clear-no-files',
          });
        } else {
          appendNotice({
            message: sprintf(
              /* translators: %d: number of minified files that were deleted. */
              _n(
                'Cleared %d minified file.',
                'Cleared %d minified files.',
                filesDeleted,
                'pressidium-performance',
              ),
              filesDeleted,
            ),
            status: 'success',
            id: 'minification-clear-success',
          });
        }

        setRefreshToken((previousToken) => previousToken + 1);
      } else {
        appendNotice({
          message: __('Could not clear minified files.', 'pressidium-performance'),
          status: 'error',
          id: 'minification-clear-error',
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
          message: __('Could not clear minified files.', 'pressidium-performance'),
          status: 'error',
          id: 'minification-clear-generic-error',
        });
      }
    } finally {
      setIsClearing(false);
    }
  }, [appendNotice]);

  return (
    <Panel>
      <PanelBody initialOpen>
        <PanelRow>
          <p>
            {__('The minification feature reduces the size of your website’s CSS and JavaScript files by removing unnecessary spaces, comments, and formatting. This makes your pages load faster and improves overall performance without changing how your website looks or works.', 'pressidium-performance')}
          </p>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Configuration', 'pressidium-performance')}
        icon={SettingsIcon}
        initialOpen
      >
        <PanelRow>
          <ToggleControl
            label={__('Minify scripts', 'pressidium-performance')}
            help={state.minification.minifyJS
              ? __('Will minify JS files', 'pressidium-performance')
              : __('Won\'t minify JS files', 'pressidium-performance')}
            checked={state.minification.minifyJS}
            className="pressidium-toggle-control"
            onChange={(value) => onMinificationSettingChange('minifyJS', value)}
          />
        </PanelRow>
        <PanelRow>
          <ToggleControl
            label={__('Minify stylesheets', 'pressidium-performance')}
            help={state.minification.minifyCSS
              ? __('Will minify CSS files', 'pressidium-performance')
              : __('Won\'t minify CSS files', 'pressidium-performance')}
            checked={state.minification.minifyCSS}
            className="pressidium-toggle-control"
            onChange={(value) => onMinificationSettingChange('minifyCSS', value)}
          />
        </PanelRow>
        <PanelRow>
          <ToggleControl
            label={__('Only minify assets from this site', 'pressidium-performance')}
            help={state.minification.sameOriginOnly
              ? __('Third-party scripts and stylesheets are left untouched', 'pressidium-performance')
              : __('Will download, minify and self-host assets from other domains', 'pressidium-performance')}
            checked={state.minification.sameOriginOnly}
            className="pressidium-toggle-control"
            onChange={(value) => onMinificationSettingChange('sameOriginOnly', value)}
          />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Script exclusions', 'pressidium-performance')}
        icon={CodeIcon}
        initialOpen
      >
        <PanelRow>
          <MinificationExclusionsTable category="js" />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Stylesheet exclusions', 'pressidium-performance')}
        icon={BrushIcon}
        initialOpen
      >
        <PanelRow>
          <MinificationExclusionsTable category="css" />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Minifications', 'pressidium-performance')}
        icon={ArchiveIcon}
        initialOpen
      >
        <PanelRow>
          <MinificationsTable key={refreshToken} />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Maintenance', 'pressidium-performance')}
        icon={ToolIcon}
        initialOpen
      >
        <PanelRow>
          <Flex direction="column" gap={4} style={{ width: '100%' }}>
            <FlexItem>
              <p style={{ margin: 0 }}>
                {__('Clearing minified files deletes all minified files, their database records, and any cached decisions about files that should skip minification. Your files will be minified again as your pages are visited. Use this to resolve issues caused by stale or broken minified files.', 'pressidium-performance')}
              </p>
            </FlexItem>
            <FlexItem>
              <Flex justify="flex-start">
                <FlexItem>
                  <Button
                    variant="secondary"
                    className="is-destructive"
                    onClick={openClearModal}
                    disabled={isClearing}
                    isBusy={isClearing}
                  >
                    {__('Clear minified files', 'pressidium-performance')}
                  </Button>
                  <ClearMinifiedFilesModal
                    isOpen={isClearModalOpen}
                    onClose={closeClearModal}
                    clearMinifiedFiles={clearMinifiedFiles}
                  />
                </FlexItem>
              </Flex>
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>

    </Panel>
  );
}

export default MinificationTab;
