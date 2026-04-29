import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal,
} from '@wordpress/components';

function RevertOptimizedImagesModal(props) {
  const { isOpen, onClose, revertOptimizedImages } = props;

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Revert optimized images?', 'pressidium-performance')}
      onRequestClose={onClose}
    >
      <Flex direction="column">
        <p>
          <strong>
            {__('This action cannot be undone.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {__('You are about to revert all optimized images back to their original state in the background. The optimized files will be deleted and the original metadata will be restored. Are you sure you want to proceed?', 'pressidium-performance')}
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
              isDestructive
              onClick={() => {
                revertOptimizedImages();
                onClose();
              }}
            >
              {__('Revert images', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </Flex>
    </Modal>
  );
}

export default RevertOptimizedImagesModal;
