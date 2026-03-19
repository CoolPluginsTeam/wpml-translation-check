import ReactDOM from 'react-dom';

const extractInnerContent = async (element) => {
    const tempDiv = document.createElement('div');
    const root = ReactDOM.createRoot(tempDiv);

  return new Promise((resolve) => {
    root.render(element);
    setTimeout(() => {
      const html = tempDiv.innerHTML;
      root.unmount();
      tempDiv.remove();
      resolve(html);
    }, 0);
  });
};

export default extractInnerContent;

