import { useState, useCallback, createInterpolateElement } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  commentAuthorAvatar as SignUpIcon,
  linkOff as SignOutIcon,
} from '@wordpress/icons';

import apiFetch from '@wordpress/api-fetch';

import EdgeConnectModal from 'components/modals/EdgeConnectModal.js';
import DisconnectEdgeModal from 'components/modals/DisconnectEdgeModal.js';

function EdgeConnect(props) {
  const { hasCredentials, setHasCredentials, appendNotice } = props;

  const [isConnectModalOpen, setIsConnectModalOpen] = useState(false);
  const [isDisconnectModalOpen, setIsDisconnectModalOpen] = useState(false);

  const openConnectModal = useCallback(() => setIsConnectModalOpen(true), []);
  const closeConnectModal = useCallback(() => setIsConnectModalOpen(false), []);

  const openDisconnectModal = useCallback(() => setIsDisconnectModalOpen(true), []);
  const closeDisconnectModal = useCallback(() => setIsDisconnectModalOpen(false), []);

  const urls = {
    signup: 'https://pressidium.com/edge-pricing/',
  };

  const connectToEdge = useCallback(async (clientId, clientSecret) => {
    const { edge_credentials_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: {
        client_id: clientId,
        client_secret: clientSecret,
        nonce,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Site connected to Pressidium EDGE successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'edge-connected-success',
        });

        setHasCredentials(true);
      } else {
        appendNotice({
          message: __('Could not connect this site to Pressidium EDGE.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-not-connected-error',
        });
      }
    } catch (error) {
      if ('code' in error && 'message' in error) {
        appendNotice({
          message: error.message,
          status: 'error',
          id: `edge-not-connected-${error.code}-error`,
        });
      } else {
        appendNotice({
          message: __('Could not connect this site to Pressidium EDGE.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-not-connected-generic-error',
        });
      }
    }
  }, []);

  const disconnectFromEdge = useCallback(async () => {
    const { edge_credentials_route: route, nonce } = pressidiumPerfAdminDetails.api;

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
          message: __('Site disconnected from Pressidium EDGE successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'edge-disconnected-success',
        });

        setHasCredentials(false);
      } else {
        appendNotice({
          message: __('Could not disconnect this site from Pressidium EDGE.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-not-disconnected-error',
        });
      }
    } catch (error) {
      if ('code' in error && 'message' in error) {
        appendNotice({
          message: error.message,
          status: 'error',
          id: `edge-not-disconnected-${error.code}-error`,
        });
      } else {
        appendNotice({
          message: __('Could not disconnect this site from Pressidium EDGE.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-not-disconnected-generic-error',
        });
      }
    }
  }, []);

  if (hasCredentials) {
    return (
      <>
        <div className="pressidium-edge-hero__status-left">
          <span className="pressidium-edge-hero__status-dot" aria-hidden="true">&#10003;</span>
          <div>
            <div className="pressidium-edge-hero__status-label">
              {__('Connected', 'pressidium-performance')}
            </div>
            <div className="pressidium-edge-hero__status-sublabel">
              {__('Your site is connected to Pressidium EDGE.', 'pressidium-performance')}
            </div>
          </div>
        </div>
        <Button
          variant="secondary"
          className="is-destructive"
          icon={SignOutIcon}
          onClick={openDisconnectModal}
        >
          {__('Disconnect from Pressidium EDGE', 'pressidium-performance')}
        </Button>
        <DisconnectEdgeModal
          isOpen={isDisconnectModalOpen}
          onClose={closeDisconnectModal}
          disconnectFromEdge={disconnectFromEdge}
        />
      </>
    );
  }

  return (
    <>
      <div className="pressidium-edge-hero__status-left">
        <Button
          icon={SignUpIcon}
          href={urls.signup}
          target="_blank"
          isPrimary
        >
          {__('Create your Pressidium EDGE account', 'pressidium-performance')}
        </Button>
        <p>
          {
            createInterpolateElement(
              __('Already using Pressidium EDGE? <a>Connect your website!</a>', 'pressidium-performance'),
              {
                a: (
                  // eslint-disable-next-line max-len
                  // eslint-disable-next-line jsx-a11y/anchor-has-content,jsx-a11y/control-has-associated-label
                  <a
                    href="#"
                    rel="noreferrer noopener"
                    onClick={openConnectModal}
                  />
                ),
              },
            )
          }
        </p>
      </div>
      <EdgeConnectModal
        isOpen={isConnectModalOpen}
        onClose={closeConnectModal}
        connectToEdge={connectToEdge}
      />
    </>
  );
}

export default EdgeConnect;
