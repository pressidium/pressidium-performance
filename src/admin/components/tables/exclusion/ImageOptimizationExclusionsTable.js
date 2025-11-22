import { useContext, useMemo, useCallback } from '@wordpress/element';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import ExclusionsTable from './ExclusionsTable';

function ImageOptimizationExclusionsTable() {
  const { state, dispatch } = useContext(SettingsContext);

  const exclusions = useMemo(() => state.imageOptimization.exclusions || [], [state]);

  const onAddExclusion = useCallback(() => {
    dispatch({
      type: ActionTypes.ADD_IMAGE_OPTIMIZATION_EXCLUSION,
    });
  }, []);

  const onUpdateExclusion = useCallback((index, key, value) => {
    dispatch({
      type: ActionTypes.UPDATE_IMAGE_OPTIMIZATION_EXCLUSION,
      payload: {
        index,
        key,
        value,
      },
    });
  }, []);

  const onDeleteExclusion = useCallback((index) => {
    dispatch({
      type: ActionTypes.DELETE_IMAGE_OPTIMIZATION_EXCLUSION,
      payload: {
        index,
      },
    });
  }, []);

  return (
    <ExclusionsTable
      exclusions={exclusions}
      onAddExclusion={onAddExclusion}
      onUpdateExclusion={onUpdateExclusion}
      onDeleteExclusion={onDeleteExclusion}
    />
  );
}

export default ImageOptimizationExclusionsTable;
