interface MultiMediaUploaderResponseInterface {
  ids: number[];
  urls: string[];
  html: string;
}

interface SingleMediaUploaderResponseInterface {
  id: number;
  url: string;
  html: string;
}

const listItemTemplate = (src: string): string => {
  return '<li><img src=\'' + src + '\' alt="" width=\'150\' height=\'150\' class=\'attachment-150x150 size-150x150\' loading=\'lazy\' /></li>';
}

const wpMediaUploader = (params = {}): Promise<SingleMediaUploaderResponseInterface | MultiMediaUploaderResponseInterface> => {
  const defaultArgs = {
    title: 'Add Image',
    buttonText: 'Use image',
    type: 'image',
    multiple: false,
  }
  const args = Object.assign(defaultArgs, params);
  return new Promise(resolve => {
    let frame = new window.wp.media.view.MediaFrame.Select({
      title: args.title,
      multiple: args.multiple,
      library: {
        order: 'ASC',
        orderby: 'title',
        type: args.type,
        search: null,
        uploadedTo: null
      },

      button: {text: args.buttonText}
    });

    frame.on('select', function () {
      let collection = frame.state().get('selection'),
        ids: number[] = [],
        urls: string[] = [],
        html: string = '';

      collection.each(function (attachment: { id: number, attributes: Record<string, any> }) {
        ids.push(attachment.id);
        if ('video' === attachment.attributes.type) {
          let src = attachment.attributes.thumb.src || attachment.attributes.image.src;
          html += listItemTemplate(src);
          urls.push(src)
        } else if ('image' === attachment.attributes.type) {
          let src = attachment.attributes.sizes.thumbnail.url || attachment.attributes.sizes.full.url;
          html += listItemTemplate(src);
          urls.push(src)
        }
      });

      if (args.multiple) {
        const data = {ids: ids, urls: urls, html: html}
        resolve(data);
      } else {
        resolve({
          id: ids.length ? ids[0] : 0,
          url: urls.length ? urls[0] : '',
          html: html
        })
      }
    });

    frame.open();
  })
}

export type {
  MultiMediaUploaderResponseInterface,
  SingleMediaUploaderResponseInterface
}
export default wpMediaUploader;
