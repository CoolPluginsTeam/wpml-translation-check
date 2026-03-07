import React from 'react';
import filterContent from '../../filter-target-content';
import extractInnerContent from '../extarct-inner-content';
import saveSourceString from '../../store-source-string';

/**
 * @param {Object} content The content to filter
 * @param {string} service The service provider
 * @param {Object} blockParseRules The block parse rules
 * @param {string} postId The post ID
 * @param {Object} storeDispatch The store dispatch
 * @param {Object} filterHtmlContent The filter HTML content
 * @param {string} sourceLanguage The source language
 * @returns {Object} The filtered content
 */
const FilterGutenbergContent = async ({content, service, blockParseRules, postId, storeDispatch, filterHtmlContent, sourceLanguage}) => {
    const allowedBlocks=Object.keys(blockParseRules?.AtfpBlockParseRules);

    const loopCallback=async (callback, loop, index)=>{
        await callback(loop[index], index);

        index++;

        if(index < loop.length){
            await loopCallback(callback, loop, index);
        }
    }

    const getStringContent=async (content, contentKey, skipTags=[]) =>{
        let stringContent=content;
        if(filterHtmlContent){

            let reactElement=filterContent({content, service, contentKey, skipTags});
            
            stringContent=await extractInnerContent(reactElement);
            
            reactElement=null;
        }

        return stringContent;
    }

    const storeSourceString=async (contentKey, content, targetContent)=>{
        saveSourceString(postId, contentKey, content, targetContent, storeDispatch);
    }

    /**
     * @param {Array} keys
     * @param {Object} blockContent
     */
    const filterBlockContent=async (keys, blockContent)=>{
        let innerContentTransalted=false;

        let transltedStrings=[];
        if(allowedBlocks.includes(blockContent.blockName)){
            if(blockContent.attrs && Object.keys(blockContent.attrs).length > 0){
                transltedStrings = await filterBlockAttr([...keys, 'attrs'], blockContent, blockParseRules?.AtfpBlockParseRules[blockContent.blockName]);
            }
        }

        if(blockContent.innerContent && blockContent.innerContent.length > 0 && transltedStrings.length > 0){
            const joinInnerContent=blockContent.innerContent.join(' ');

            let tempNode=document.createElement('div');
            tempNode.innerHTML=joinInnerContent;

            const totalWords=tempNode.innerText.split(' ').length;
            let translatedWords=0;

            transltedStrings.forEach(item=>{
                if(tempNode.innerText.includes(item)){
                    translatedWords+=item.split(' ').length;
                }
            });

            const translatedPercentage=translatedWords * 100 / totalWords;

            if(translatedPercentage > 97){
                innerContentTransalted = true;
            }

            tempNode=null;
        }

        if(blockContent.innerContent && blockContent.innerContent.length > 0 && !innerContentTransalted){
            let innerHTML=blockContent.innerHTML ? blockContent.innerHTML.trim() : '';
            innerHTML=innerHTML.replace(/\s/g, '');

            if(innerHTML && '' !== innerHTML){
                await filterBlockInnerContent([...keys,'innerContent'], blockContent.innerContent);
            }
        }
        
        if(blockContent.innerBlocks && blockContent.innerBlocks.length > 0){
            const runLoopAsyncInner=async(innerBlock, index)=>{
                await filterBlockContent([...keys, 'innerBlocks', index], innerBlock);
            }

            await loopCallback(runLoopAsyncInner, blockContent.innerBlocks, 0);
        }
    }

    /**
     * @param {Array} keys
     * @param {Object} innerContent
     */
    const filterBlockInnerContent=async (keys, innerContent)=>{

        const runLoopAsyncInner=async(content, index)=>{
            let tempNode=document.createElement('div');
            tempNode.innerHTML=content;
        
            if(tempNode.innerText.trim() === '') return;

            tempNode=null;

            let string=content ? content.trim() : '';
            
            string=string.replace(/\s/g, '');
            if(string && '' !== string){
                const uniqueKey=[...keys, index].join('_automl_wpml_');
                const stringContent=await getStringContent(innerContent[index], uniqueKey, ['script', 'style']);

                if(stringContent && '' !== stringContent){ 
                    storeSourceString(uniqueKey, innerContent[index], stringContent);
                }
            }
        }

        await loopCallback(runLoopAsyncInner, innerContent, 0);
    }

    /**
     * @param {Object} keyType
     * @param {Array} keys
     * @param {Object} currentBlock
     */
    const filterBlockObjectAttr=async (blockRule, keys, currentBlock, translatedKeys=[])=>{

        if(Object.getPrototypeOf(blockRule) === Object.prototype){
            const blockRuleKeys=Object.keys(blockRule);

            const runLoopAsyncInner=async(key, index)=>{
                if(typeof blockRule[key] === 'boolean' && true === blockRule[key] && currentBlock && currentBlock[key]){
                    const uniqueKey=[...keys, key].join('_automl_wpml_');
                    const stringContent=await getStringContent(currentBlock[key], uniqueKey);

                    if(stringContent && '' !== stringContent){
                        translatedKeys.push(stringContent);
                        storeSourceString(uniqueKey, currentBlock[key], stringContent);
                    }
                }else if(Object.getPrototypeOf(blockRule[key]) === Object.prototype || Object.getPrototypeOf(blockRule[key]) === Array.prototype){
                    await filterBlockObjectAttr(blockRule[key], [...keys, key], currentBlock[key]);
                }
            }

            await loopCallback(runLoopAsyncInner, blockRuleKeys, 0);
        }else if(Object.getPrototypeOf(blockRule) === Array.prototype){
            const runLoopAsyncInner=async(item, index)=>{
                if(typeof blockRule[0] === 'boolean' && true === blockRule[0]){
                    const uniqueKey=[...keys, index].join('_automl_wpml_');

                    const stringContent=await getStringContent(item, uniqueKey);

                    if(stringContent && '' !== stringContent){
                        translatedKeys.push(stringContent);
                        storeSourceString(uniqueKey, item, stringContent);
                    }
                }else if(Object.getPrototypeOf(blockRule[0]) === Object.prototype || Object.getPrototypeOf(blockRule[0]) === Array.prototype){
                    await filterBlockObjectAttr(blockRule[0], [...keys, index], item, translatedKeys);
                }
            }

            await loopCallback(runLoopAsyncInner, currentBlock, 0);
        }
    }

    /**
     * @param {Array} keys
     * @param {Object} block
     * @param {Object} blockRule
     */
    const filterBlockAttr=async (keys, block, blockRule)=>{

        const translatedKeys = [];

        let currentBlock=block?.attrs;

        const attributeKeys=Object.keys(currentBlock);
        const blockRules=Object.keys(blockRule?.attributes);

        const allowedAttributeKeys=blockRules.filter(key=>attributeKeys.includes(key));

        const runLoopAsyncAttr=async(key, index)=>{
            const activeBlockRule=blockRule?.attributes[key];
            const currentKey = JSON.parse(JSON.stringify(keys));

            if(true === activeBlockRule){
                currentKey.push(key);
                const uniqueKey=currentKey.join('_automl_wpml_');
                if(currentBlock[key] && '' !== currentBlock[key]){
                    const stringContent=await getStringContent(currentBlock[key], uniqueKey);

                    if(stringContent && '' !== stringContent){
                        translatedKeys.push(stringContent);
                        storeSourceString(uniqueKey, currentBlock[key], stringContent);
                    }
                }
            }else if(typeof activeBlockRule === 'object'){
                await filterBlockObjectAttr(activeBlockRule, [...keys, key], currentBlock[key], translatedKeys);
            }
        }

        await loopCallback(runLoopAsyncAttr, allowedAttributeKeys, 0);

        return translatedKeys;
    }

    /**
     * @param {Object} content
     */
    const filterContentObject=async (content)=>{
        const runLoopAsync=async(key, index)=>{
            if(allowedBlocks.includes(content[key].blockName) && content[key].attrs){
                await filterBlockContent(['content', key], content[key], blockParseRules?.AtfpBlockParseRules[content[key].blockName]);
            }else if(content[key].innerBlocks && content[key].innerBlocks.length > 0){
                const runLoopAsyncInner=async(innerBlock, index)=>{
                    await filterBlockContent(['content', key,'innerBlocks', index], innerBlock);
                }

                await loopCallback(runLoopAsyncInner, content[key].innerBlocks, 0);
            }
        }

        await loopCallback(runLoopAsync, Object.keys(content), 0);
    }

    if(typeof content === 'object' && content !== null && Object.keys(content).length > 0){
        await filterContentObject(content);
    }
}

export default FilterGutenbergContent;
