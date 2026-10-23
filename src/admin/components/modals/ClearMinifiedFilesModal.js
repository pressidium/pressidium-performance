import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal,
} from '@wordpress/components';

function ClearMinifiedFilesModal(props) {
  const { isOpen, onClose, clearMinifiedFiles } = props;

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Clear minified files?', 'pressidium-performance')}
      onRequestClose={onClose}
      size="medium"
    >
      <Flex direction="column">
        <p>
          <strong>
            {__('This action cannot be undone.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {__('You are about to delete all minified files, their database records, and any cached decisions about files that should skip minification. Your files will be minified again as your pages are visited. Are you sure you want to proceed?', 'pressidium-performance')}
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
                clearMinifiedFiles();
                onClose();
              }}
            >
              {__('Clear minified files', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </Flex>
    </Modal>
  );
}

export default ClearMinifiedFilesModal;
