import { useContext, useMemo, useCallback } from '@wordpress/element';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import ExclusionsTable from './ExclusionsTable';

function MinificationExclusionsTable(props) {
  const { category = 'js' } = props;

  const { state, dispatch } = useContext(SettingsContext);

  const exclusions = useMemo(
    () => {
      if (!state.minification.exclusions) {
        return [];
      }

      return state.minification.exclusions[category];
    },
    [state, category],
  );

  const onAddExclusion = useCallback(() => {
    dispatch({
      type: ActionTypes.ADD_MINIFICATION_EXCLUSION,
      payload: {
        category,
      },
    });
  }, [category]);

  const onUpdateExclusion = useCallback((index, key, value) => {
    dispatch({
      type: ActionTypes.UPDATE_MINIFICATION_EXCLUSION,
      payload: {
        category,
        index,
        key,
        value,
      },
    });
  }, [category]);

  const onDeleteExclusion = useCallback((index) => {
    dispatch({
      type: ActionTypes.DELETE_MINIFICATION_EXCLUSION,
      payload: {
        category,
        index,
      },
    });
  }, [category]);

  return (
    <ExclusionsTable
      exclusions={exclusions}
      onAddExclusion={onAddExclusion}
      onUpdateExclusion={onUpdateExclusion}
      onDeleteExclusion={onDeleteExclusion}
    />
  );
}

export default MinificationExclusionsTable;
