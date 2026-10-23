import { useState, useContext, useCallback } from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  ToggleControl,
  Notice,
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

import styled from 'styled-components';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import ConcatenationExclusionsTable from 'components/tables/exclusion/ConcatenationExclusionsTable';
import ConcatenationsTable from 'components/tables/ConcatenationsTable';
import ClearConcatenatedFilesModal from 'components/modals/ClearConcatenatedFilesModal';

const StyledNotice = styled(Notice)`
  margin: 0 !important;
  border-width: 1px;
  border-style: solid;
  border-color: #e0e0e0;
  border-bottom: none;
  border-radius: 0;
  box-shadow: inset 4px 0 0 0 #f0b849;
`;

function ConcatenationTab(props) {
  const { appendNotice } = props;

  const { state, dispatch } = useContext(SettingsContext);

  const [noticeDismissed, setNoticeDismissed] = useState(false);

  const [isClearing, setIsClearing] = useState(false);
  const [isClearModalOpen, setIsClearModalOpen] = useState(false);

  /*
   * Bumped after a successful clear. It's used as the `key` of the concatenations
   * table, so that bumping it remounts the table and resets its internal state.
   */
  const [refreshToken, setRefreshToken] = useState(0);

  const onConcatenationSettingChange = useCallback((key, value) => {
    dispatch({
      type: ActionTypes.SET_CONCATENATION_SETTING,
      payload: {
        key,
        value,
      },
    });
  }, []);

  const dismissNotice = useCallback(() => setNoticeDismissed(true), []);

  const openClearModal = useCallback(() => setIsClearModalOpen(true), []);
  const closeClearModal = useCallback(() => setIsClearModalOpen(false), []);

  const clearConcatenatedFiles = useCallback(async () => {
    setIsClearing(true);

    const { concatenation_clear_route: route, nonce } = pressidiumPerfAdminDetails.api;

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
            message: __('No concatenated files were found to clear.', 'pressidium-performance'),
            status: 'info',
            id: 'concatenation-clear-no-files',
          });
        } else {
          appendNotice({
            message: sprintf(
              /* translators: %d: number of concatenated files that were deleted. */
              _n(
                'Cleared %d concatenated file.',
                'Cleared %d concatenated files.',
                filesDeleted,
                'pressidium-performance',
              ),
              filesDeleted,
            ),
            status: 'success',
            id: 'concatenation-clear-success',
          });
        }

        setRefreshToken((previousToken) => previousToken + 1);
      } else {
        appendNotice({
          message: __('Could not clear concatenated files.', 'pressidium-performance'),
          status: 'error',
          id: 'concatenation-clear-error',
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
          message: __('Could not clear concatenated files.', 'pressidium-performance'),
          status: 'error',
          id: 'concatenation-clear-generic-error',
        });
      }
    } finally {
      setIsClearing(false);
    }
  }, [appendNotice]);

  return (
    <>
      {!noticeDismissed ? (
        <StyledNotice
          status="warning"
          politeness="polite"
          onDismiss={dismissNotice}
        >
          <strong>
            {__('Heads up! This is a beta feature.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {__('Concatenation might not play nicely with every theme or plugin. Use it with caution. If something breaks, try excluding the scripts or stylesheets causing trouble, or disable concatenation entirely.', 'pressidium-performance')}
        </StyledNotice>
      ) : null}
      <Panel>
        <PanelBody initialOpen>
          <PanelRow>
            <p>
              {__('The concatenation feature merges multiple CSS and JavaScript files into a single file, reducing the number of HTTP requests your website needs to make. This helps speed up your website, especially for visitors on slower connections.', 'pressidium-performance')}
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
              label={__('Concatenate scripts', 'pressidium-performance')}
              help={state.concatenation.concatenateJS
                ? __('Will concatenate JS files', 'pressidium-performance')
                : __('Won\'t concatenate JS files', 'pressidium-performance')}
              checked={state.concatenation.concatenateJS}
              className="pressidium-toggle-control"
              onChange={(value) => onConcatenationSettingChange('concatenateJS', value)}
            />
          </PanelRow>
          <PanelRow>
            <ToggleControl
              label={__('Concatenate stylesheets', 'pressidium-performance')}
              help={state.concatenation.concatenateCSS
                ? __('Will concatenate CSS files', 'pressidium-performance')
                : __('Won\'t concatenate CSS files', 'pressidium-performance')}
              checked={state.concatenation.concatenateCSS}
              className="pressidium-toggle-control"
              onChange={(value) => onConcatenationSettingChange('concatenateCSS', value)}
            />
          </PanelRow>
          <PanelRow>
            <ToggleControl
              label={__('Only concatenate assets from this site', 'pressidium-performance')}
              help={state.concatenation.sameOriginOnly
                ? __('Third-party scripts and stylesheets are left untouched', 'pressidium-performance')
                : __('Will download, concatenate and self-host assets from other domains', 'pressidium-performance')}
              checked={state.concatenation.sameOriginOnly}
              className="pressidium-toggle-control"
              onChange={(value) => onConcatenationSettingChange('sameOriginOnly', value)}
            />
          </PanelRow>
        </PanelBody>

        <PanelBody
          title={__('Script exclusions', 'pressidium-performance')}
          icon={CodeIcon}
          initialOpen
        >
          <PanelRow>
            <ConcatenationExclusionsTable category="js" />
          </PanelRow>
        </PanelBody>

        <PanelBody
          title={__('Stylesheet exclusions', 'pressidium-performance')}
          icon={BrushIcon}
          initialOpen
        >
          <PanelRow>
            <ConcatenationExclusionsTable category="css" />
          </PanelRow>
        </PanelBody>

        <PanelBody
          title={__('', 'pressidium-performance')}
          icon={ArchiveIcon}
          initialOpen
        >
          <PanelRow>
            <ConcatenationsTable key={refreshToken} />
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
                  {__('Clearing concatenated files deletes all concatenated files, their database records, and the page mappings pointing at them. Your files will be concatenated again as your pages are visited. Use this to resolve issues caused by stale or broken concatenated files.', 'pressidium-performance')}
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
                      {__('Clear concatenated files', 'pressidium-performance')}
                    </Button>
                    <ClearConcatenatedFilesModal
                      isOpen={isClearModalOpen}
                      onClose={closeClearModal}
                      clearConcatenatedFiles={clearConcatenatedFiles}
                    />
                  </FlexItem>
                </Flex>
              </FlexItem>
            </Flex>
          </PanelRow>
        </PanelBody>
      </Panel>
    </>
  );
}

export default ConcatenationTab;
