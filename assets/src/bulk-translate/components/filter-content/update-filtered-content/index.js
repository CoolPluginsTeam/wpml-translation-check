import {selectTranslatedContent} from '../../../redux-store/features/selectors';
import {store} from '../../../redux-store/store';

const updateFilteredContent = async ({ source, lang, translatedContent, postId, service }) => {
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
        const stateValue = selectTranslatedContent(store.getState(), postId, key, lang, service);
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

    const updateHtmlNodeContent= (html, key, text, positions=[key]) => {
        const trimmerHTML=html.trim();
        const hasTag = /<[^>]+>/

        if(!hasTag.test(trimmerHTML)){
            return getTransaltedValue('content_atfpp_'+positions.join('_')) ?? html;
        }

        if(text !== '' && trimmerHTML !== '' && (!trimmerHTML.startsWith('<') || !trimmerHTML.endsWith('>'))){
            return getTransaltedValue('content_atfpp_'+positions.join('_')) ?? html;

        }
        
        let tempNode=document.createElement('div');
        tempNode.innerHTML=html;

        if(tempNode.childNodes.length < 0 && tempNode.innerText.trim() !== ''){
            tempNode=null;
            return getTransaltedValue('content_atfpp_'+positions.join('_')) ?? html;
        }
        
        for(let i=0; i<tempNode.childNodes.length; i++){
            let childNode=tempNode.childNodes[i];
            
            if(childNode && childNode.tagName && ['STYLE', 'SCRIPT'].includes(childNode.tagName.toUpperCase())){
                continue;
            }

            positions.push(i);

            if(childNode && childNode.innerHTML){
                const trimmedHTML=childNode.innerHTML.trim();
                const innerText=childNode.innerText ? childNode.innerText.trim() : '';
    
                // Check if the HTML are start and end with html tags
                if(new RegExp(/^\s*(?!<)[\s\S]*\p{L}[\s\S]*$/u).test(trimmedHTML) && innerText !== ''){
                    childNode.innerHTML = getTransaltedValue('content_atfpp_'+positions.join('_')) ?? childNode.innerHTML;
                }else if(innerText !== '' && trimmedHTML.startsWith('<') && trimmedHTML.endsWith('>')){
                    childNode.innerHTML = updateHtmlNodeContent(childNode.innerHTML, key, text, positions);
                }
            }
            
        }

        const updateContetn= tempNode.innerHTML;
        tempNode=null;
        return updateContetn;
    }

    /**
     * @param {Object} source
     * @param {Object} translation
     */
    const updateContent = (source, translation) => {
        let updatedHtmlContentKeys=[];
        Object.keys(translation).forEach(key => {
            const keys = key.split('_atfpp_');
            if (keys[0] === 'title' && source.title) {
                updateTitle(source, translation[keys[0]]);
            } else if (keys[0] === 'post_name' && source.post_name) {
                updatePostName(source, translation[keys[0]]);
            } else if (keys[0] === 'excerpt' && source.excerpt) {
                updateExcerpt(source, translation[keys[0]]);
            } else if (keys[0] === 'content' && source.content) {
                if(!['openai_ai', 'google_ai'].includes(service)) {
                    const translateValue = getTransaltedValue(key);
    
                    if(source.content[keys[1]]) {
                        replaceValue(source.content[keys[1]], translateValue);
                    }
                    return;
                }

                const uniqueId=keys[1].split('_')[0];

                if(source.content[uniqueId] && source.content[uniqueId].html && typeof source.content[uniqueId].html === 'string' && source.content[uniqueId].html !== '') {
                
                    if(updatedHtmlContentKeys.includes(uniqueId)){
                        return;
                    }
    
                    if(keys[1].includes('_')){
                        updatedHtmlContentKeys.push(uniqueId);
                    }
                    
                    source.content[uniqueId].html = updateHtmlNodeContent(source.content[uniqueId].html, key, source.content[uniqueId].text, [uniqueId]);
                }
            }
        });

        updatedHtmlContentKeys=null;
    }

    updateContent(source, translatedContent);
    return source;
}

export default updateFilteredContent;