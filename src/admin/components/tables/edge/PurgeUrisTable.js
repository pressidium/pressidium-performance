import { useContext, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import EdgeItemsTable from './EdgeItemsTable';

function PurgeUrisTable() {
  const { state, dispatch } = useContext(SettingsContext);

  const uris = useMemo(
    () => {
      if (!state.edge.urisToPurge) {
        return [];
      }

      return state.edge.urisToPurge;
    },
    [state],
  );

  const onAddUri = useCallback(() => {
    dispatch({
      type: ActionTypes.ADD_URI_TO_PURGE,
    });
  }, []);

  const onUpdateUri = useCallback((index, key, value) => {
    dispatch({
      type: ActionTypes.UPDATE_URI_TO_PURGE,
      payload: {
        index,
        key,
        value,
      },
    });
  }, []);

  const onDeleteUri = useCallback((index) => {
    dispatch({
      type: ActionTypes.DELETE_URI_TO_PURGE,
      payload: {
        index,
      },
    });
  }, []);

  return (
    <EdgeItemsTable
      items={uris}
      onAddItem={onAddUri}
      onUpdateItem={onUpdateUri}
      onDeleteItem={onDeleteUri}
      labels={{
        column: __('URI', 'pressidium-performance'),
        placeholder: __('URIs to purge', 'pressidium-performance'),
        noItems: __('No URLs set.', 'pressidium-performance'),
        newItem: __('New URL', 'pressidium-performance'),
      }}
    />
  );
}

export default PurgeUrisTable;
