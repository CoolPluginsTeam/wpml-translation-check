import { configureStore } from '@reduxjs/toolkit';
import bulkTranslateStore from './features/actions';

const store = configureStore({
    reducer: bulkTranslateStore,
});

export { store };
