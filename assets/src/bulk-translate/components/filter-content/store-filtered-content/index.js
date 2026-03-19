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

    const storeHtmlNodeContent= (html, key, text, positions=[key]) => {
        const trimmerHTML=html.trim();
        const hasTag = /<[^>]+>/

        if(!hasTag.test(trimmerHTML)){
            storeSourceString('content_atfpp_'+positions.join('_'), html, html);
            return;
        }

        if(text !== '' && trimmerHTML !== '' && (!trimmerHTML.startsWith('<') || !trimmerHTML.endsWith('>'))){
            storeSourceString('content_atfpp_'+positions.join('_'), html, html);
            return;
        }
        
        let tempNode=document.createElement('div');
        tempNode.innerHTML=html;

        if(tempNode.childNodes.length < 0 && tempNode.innerText.trim() !== ''){
            tempNode=null;
            storeSourceString('content_atfpp_'+positions.join('_'), tempNode.innerHTML, tempNode.innerHTML);
            return;
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
                    storeSourceString('content_atfpp_'+positions.join('_'), childNode.innerHTML, childNode.innerHTML);
                }else if(innerText !== '' && trimmedHTML.startsWith('<') && trimmedHTML.endsWith('>')){
                    storeHtmlNodeContent(childNode.innerHTML, key, text, positions);
                }
            }
            
        }

        tempNode=null;
    }

    const runLoopAsync=async (key, index) => {
        if(content[key] && content[key].html && content[key].html !== '') {
            let stringContent = content[key];
            if(filterHtmlContent && !['openai_ai', 'google_ai'].includes(service)) {
                stringContent = await getStringContent(content[key].html, key, content[key].text);
            }else{
                storeHtmlNodeContent(content[key].html, key, content[key].text, [key]);
                return;
            }

            storeSourceString('content_atfpp_'+key, content[key].html, stringContent);
        }
    }

    await LoopCallback({callback: runLoopAsync, loop: Object.keys(content), index: 0});

    return content;
}

export default storeFilteredContent;