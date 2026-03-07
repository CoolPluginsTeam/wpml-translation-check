import filterContent from '../../filter-target-content';
import extractInnerContent from '../extarct-inner-content';
import saveSourceString from '../../store-source-string';
import LoopCallback from '../../loop-callback';

const storeFilteredContent = async ({content, service, postId, storeDispatch, filterHtmlContent, sourceLanguage}) => {

    if(!content || content === '' || Object.keys(content).length === 0) {
        return content;
    }

    const storeSourceString= (contentKey, content, targetContent)=>{
        saveSourceString(postId, contentKey, content, targetContent, storeDispatch);
    }

    const getStringContent=async (content, contentKey, texts) =>{
        let stringContent=content;
        if(filterHtmlContent){
            let reactElement=filterContent({content, service, contentKey, translatableTexts: texts, skipTags:['script', 'style']});
            stringContent=await extractInnerContent(reactElement);

            reactElement=null;
        }

        return stringContent;
    }

    const runLoopAsync=async (key, index) => {
        if(content[key] && content[key].html && content[key].html !== '') {
            let stringContent = content[key];
            if(filterHtmlContent) {
                stringContent = await getStringContent(content[key].html, key, content[key].text);
            }

            storeSourceString('content_atfpp_'+key, content[key].html, stringContent);
        }
    }

    await LoopCallback({callback: runLoopAsync, loop: Object.keys(content), index: 0});

    return content;
}

export default storeFilteredContent;