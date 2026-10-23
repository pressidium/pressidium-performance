import { useState, useCallback, createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal, TextControl,
} from '@wordpress/components';
import {
  login as SignInIcon,
} from '@wordpress/icons';

const NO_ERRORS = {
  clientId: '',
  clientSecret: '',
};

function EdgeConnectModal(props) {
  const { isOpen, onClose, connectToEdge } = props;

  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');
  const [errors, setErrors] = useState(NO_ERRORS);

  const urls = {
    edgeDashboard: 'https://dashboard.pressidium.com/',
  };

  /*
   * Credentials never contain leading and/or trailing whitespace, and
   * they are usually pasted in rather than typed, so we trim as we go.
   */
  const onClientIdChange = useCallback((value) => {
    setClientId(value.trim());
    setErrors((prevErrors) => ({ ...prevErrors, clientId: '' }));
  }, []);

  const onClientSecretChange = useCallback((value) => {
    setClientSecret(value.trim());
    setErrors((prevErrors) => ({ ...prevErrors, clientSecret: '' }));
  }, []);

  const closeModal = useCallback(() => {
    // Clean any errors so they won't be displayed the next time the modal is opened
    setErrors(NO_ERRORS);

    onClose();
  }, [onClose]);

  const onConnect = useCallback(() => {
    const nextErrors = {
      clientId: clientId === ''
        ? __('Please enter your client ID', 'pressidium-performance')
        : '',
      clientSecret: clientSecret === ''
        ? __('Please enter your client secret', 'pressidium-performance')
        : '',
    };

    if (nextErrors.clientId || nextErrors.clientSecret) {
      // Missing credentials, display an error for each empty field
      setErrors(nextErrors);
      return;
    }

    connectToEdge(clientId, clientSecret);
    closeModal();
  }, [clientId, clientSecret, connectToEdge, closeModal]);

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Integrate your site with Pressidium EDGE', 'pressidium-performance')}
      onRequestClose={closeModal}
    >
      <Flex direction="column">
        <FlexItem>
          <p>
            {
              createInterpolateElement(
                __('Enter the credentials you issued in the <a>Pressidium EDGE Dashboard.</a>', 'pressidium-performance'),
                {
                  a: (
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                    <a
                      href={urls.edgeDashboard}
                      target="_blank"
                      rel="noreferrer noopener"
                    />
                  ),
                },
              )
            }
          </p>
        </FlexItem>
        <FlexItem>
          <TextControl
            value={clientId}
            placeholder={__('Client ID', 'pressidium-performance')}
            onChange={onClientIdChange}
            className={errors.clientId ? 'pressidium-text-control--invalid' : undefined}
            help={errors.clientId || undefined}
            aria-invalid={Boolean(errors.clientId)}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </FlexItem>
        <FlexItem>
          <TextControl
            value={clientSecret}
            placeholder={__('Client secret', 'pressidium-performance')}
            onChange={onClientSecretChange}
            className={errors.clientSecret ? 'pressidium-text-control--invalid' : undefined}
            help={errors.clientSecret || undefined}
            aria-invalid={Boolean(errors.clientSecret)}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </FlexItem>
        <FlexItem style={{ marginTop: '16px' }}>
          <Flex justify="flex-end">
            <FlexItem>
              <Button
                variant="tertiary"
                onClick={closeModal}
              >
                {__('Cancel', 'pressidium-performance')}
              </Button>
            </FlexItem>
            <FlexItem>
              <Button
                icon={SignInIcon}
                variant="primary"
                onClick={onConnect}
              >
                {__('Connect to Pressidium EDGE', 'pressidium-performance')}
              </Button>
            </FlexItem>
          </Flex>
        </FlexItem>
      </Flex>
    </Modal>
  );
}

export default EdgeConnectModal;
