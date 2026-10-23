import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal,
} from '@wordpress/components';

function ClearConcatenatedFilesModal(props) {
  const { isOpen, onClose, clearConcatenatedFiles } = props;

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Clear concatenated files?', 'pressidium-performance')}
      onRequestClose={onClose}
      size="medium"
    >
      <Flex direction="column">
        <p>
          <strong>
            {__('This action cannot be undone.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {__('You are about to delete all concatenated files, their database records, and the page mappings pointing at them. Your files will be concatenated again as your pages are visited. Are you sure you want to proceed?', 'pressidium-performance')}
        </p>
        <Flex justify="flex-end">
          <FlexItem>
            <Button
              variant="tertiary"
              onClick={onClose}
            >
              {__('Cancel', 'pressidium-performance')}
            </Button>
          </FlexItem>
          <FlexItem>
            <Button
              variant="primary"
              className="is-destructive"
              onClick={() => {
                clearConcatenatedFiles();
                onClose();
              }}
            >
              {__('Clear concatenated files', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </Flex>
    </Modal>
  );
}

export default ClearConcatenatedFilesModal;
