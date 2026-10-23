import { useContext, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import EdgeItemsTable from './EdgeItemsTable';

function PurgeCacheTagsTable() {
  const { state, dispatch } = useContext(SettingsContext);

  const cacheTags = useMemo(
    () => {
      if (!state.edge.cacheTagsToPurge) {
        return [];
      }

      return state.edge.cacheTagsToPurge;
    },
    [state],
  );

  const onAddCacheTag = useCallback(() => {
    dispatch({
      type: ActionTypes.ADD_CACHE_TAG_TO_PURGE,
    });
  }, []);

  const onUpdateCacheTag = useCallback((index, key, value) => {
    dispatch({
      type: ActionTypes.UPDATE_CACHE_TAG_TO_PURGE,
      payload: {
        index,
        key,
        value,
      },
    });
  }, []);

  const onDeleteCacheTag = useCallback((index) => {
    dispatch({
      type: ActionTypes.DELETE_CACHE_TAG_TO_PURGE,
      payload: {
        index,
      },
    });
  }, []);

  return (
    <EdgeItemsTable
      items={cacheTags}
      onAddItem={onAddCacheTag}
      onUpdateItem={onUpdateCacheTag}
      onDeleteItem={onDeleteCacheTag}
      labels={{
        column: __('Cache Tag', 'pressidium-performance'),
        placeholder: __('Cache tag to purge', 'pressidium-performance'),
        noItems: __('No cache tags set.', 'pressidium-performance'),
        newItem: __('New cache tag', 'pressidium-performance'),
      }}
    />
  );
}

export default PurgeCacheTagsTable;
