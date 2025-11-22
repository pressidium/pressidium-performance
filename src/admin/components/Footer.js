import { useState, useContext, useCallback } from '@wordpress/element';
import {
  Button,
  Flex,
  FlexItem,
  FormFileUpload,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import styled from 'styled-components';

import SettingsContext from 'store/context';

import ExportSettingsModal from 'components/modals/ExportSettingsModal';
import ResetSettingsModal from 'components/modals/ResetSettingsModal';

const StyledFooter = styled.div`
  padding: 16px;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-top: none;
`;

function Footer(props) {
  const {
    save,
    hasUnsavedChanges,
    exportSettings,
    importSettings,
    resetSettings,
  } = props;

  const [isSaving, setIsSaving] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [isResetting, setIsResetting] = useState(false);

  const [isExportSettingsModalOpen, setIsExportSettingsModalOpen] = useState(false);
  const [isResetSettingsModalOpen, setIsResetSettingsModalOpen] = useState(false);

  const { state } = useContext(SettingsContext);

  const openExportSettingsModal = useCallback(() => setIsExportSettingsModalOpen(true), []);
  const closeExportSettingsModal = useCallback(() => setIsExportSettingsModalOpen(false), []);

  const openResetSettingsModal = useCallback(() => setIsResetSettingsModalOpen(true), []);
  const closeResetSettingsModal = useCallback(() => setIsResetSettingsModalOpen(false), []);

  const onSave = useCallback(() => {
    (async () => {
      if (isSaving) {
        return;
      }

      setIsSaving(true);

      await save();

      setIsSaving(false);
    })();
  }, [isSaving, save]);

  const onExportSettings = useCallback(() => {
    (async () => {
      setIsExporting(true);

      await exportSettings();

      setIsExporting(false);
    })();
  }, [exportSettings]);

  const onExportSettingsButtonClick = useCallback(() => {
    (async () => {
      if (hasUnsavedChanges) {
        openExportSettingsModal();
      } else {
        onExportSettings();
      }
    })();
  }, [hasUnsavedChanges, exportSettings]);

  const onImportSettings = useCallback((event) => {
    (async () => {
      setIsImporting(true);

      await importSettings(event.currentTarget.files);

      setIsImporting(false);
    })();
  }, [importSettings]);

  const onResetSettings = useCallback(() => {
    (async () => {
      setIsResetting(true);

      await resetSettings();

      setIsResetting(false);
    })();
  }, [resetSettings]);

  return (
    <StyledFooter>
      <Flex justify="space-between" wrap>
        <FlexItem>
          <Flex justify="flex-start" wrap>
            <FlexItem>
              <Button
                variant="primary"
                onClick={onSave}
                isBusy={isSaving}
              >
                {__('Save', 'pressidium-performance')}
              </Button>
            </FlexItem>
          </Flex>
        </FlexItem>

        <FlexItem>
          <Flex justify="flex-start" wrap>
            <FlexItem>
              <Button
                variant="secondary"
                onClick={onExportSettingsButtonClick}
                isBusy={isExporting}
              >
                {__('Export Settings', 'pressidium-performance')}
              </Button>
              <ExportSettingsModal
                isOpen={isExportSettingsModalOpen}
                onClose={closeExportSettingsModal}
                exportSettings={onExportSettings}
              />
            </FlexItem>

            <FlexItem>
              <FormFileUpload
                variant="secondary"
                accept="application/json"
                onChange={onImportSettings}
                isBusy={isImporting}
              >
                {__('Import Settings', 'pressidium-performance')}
              </FormFileUpload>
            </FlexItem>

            <FlexItem>
              <Button
                variant="secondary"
                className="is-destructive"
                onClick={openResetSettingsModal}
                isBusy={isResetting}
              >
                {__('Reset Settings', 'pressidium-performance')}
              </Button>
              <ResetSettingsModal
                isOpen={isResetSettingsModalOpen}
                onClose={closeResetSettingsModal}
                resetSettings={onResetSettings}
              />
            </FlexItem>
          </Flex>
        </FlexItem>
      </Flex>
    </StyledFooter>
  );
}

export default Footer;
