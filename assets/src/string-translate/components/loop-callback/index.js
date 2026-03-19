const LoopCallback=async ({callback, loop, index})=>{
    await callback(loop[index], index);

    index++;

    if(index < loop.length){
        await LoopCallback({callback, loop, index});
    }
}

export default LoopCallback;