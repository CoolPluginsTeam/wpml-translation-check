import {selectTranslatedContent} from '../../../redux-store/features/selectors';
import {store} from '../../../redux-store/store';

const updateFilteredContent = async ({ source, lang, translatedContent, serviceProvider, postId }) => {
    /**
      * @param {Object} Object
      * @param {string} key
      * @param {string} translateValue
      * @returns {boolean}
      */
    const replaceValue = (Object, translateValue) => {
        if (Object && Object.html && Object.html !== '' && typeof Object.html === 'string' && Object.html.trim() !== '') {
            Object.html = translateValue;

            return true;
        }

        return false;
    }

    const getTransaltedValue = (key) => {
        const stateValue = selectTranslatedContent(store.getState(), postId, key, lang, serviceProvider);
        return stateValue;
    }

    /**
     * @param {Object} source
     * @param {string} value
     */
    const updateTitle = (source, value) => {
        if (value && '' !== value) {
            source.title = getTransaltedValue('title');
        }
    }

    /**
     * @param {Object} source
     * @param {string} value
     */
    const updatePostName = (source, value) => {
        if (value && '' !== value) {
            source.post_name = getTransaltedValue('post_name');
        }
    }

    const updateExcerpt = (source, value) => {
        if (value && '' !== value) {
            source.excerpt = getTransaltedValue('excerpt');
        }
    }

    /**
     * @param {Object} source
     * @param {Object} translation
     */
    const updateContent = (source, translation) => {
        Object.keys(translation).forEach(key => {
            const keys = key.split('_atfpp_');
            if (keys[0] === 'title' && source.title) {
                updateTitle(source, translation[keys[0]]);
            } else if (keys[0] === 'post_name' && source.post_name) {
                updatePostName(source, translation[keys[0]]);
            } else if (keys[0] === 'excerpt' && source.excerpt) {
                updateExcerpt(source, translation[keys[0]]);
            } else if (keys[0] === 'content' && source.content) {
                const translateValue = getTransaltedValue(key);

                if(source.content[keys[1]]) {
                    replaceValue(source.content[keys[1]], translateValue);
                }
            }
        });
    }

    updateContent(source, translatedContent);

    return source;
}

export default updateFilteredContent;