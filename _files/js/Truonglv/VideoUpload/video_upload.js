!function($, window, document, _undefined) {
    "use strict";

    XF.Truonglv_VideoUpload = XF.Element.newHandler({
        options: {
            attachmentHash: null,
            contextData: null,
            uploadUrl: null,
            chunkSize: 0,
            simultaneousUploads: 3,
            allowedExtensions: ''
        },

        flow: null,
        attachmentManager: null,

        init: function() {
            if (!window.Flow) {
                throw new Error('flow.js not loaded yet.');
            }

            var $form = this.$target.closest('form'),
                attachmentManager = XF.Element.getHandler($form, 'attachment-manager');

            if (attachmentManager === null) {
                throw new Error('Must be set up attachment-manager first.');
            }
            this.attachmentManager = attachmentManager;

            var flow = this.setupFlow();
            if (!flow) {
                console.error('No flow uploader support');
                return;
            }

            this.flow = flow;
            this.setupUploadButtons(this.$target, flow);
        },

        setupUploadButtons: function($uploaders, flow) {
            $uploaders.each(function()
            {
                var $button = $(this),
                    accept = $button.data('accept') || '',
                    $target = $('<span />').insertAfter($button).append($button);

                if (accept === '.') {
                    accept = '';
                } else {
                    accept = '.' + accept.toLowerCase().replace(/,/g, ',.');
                }

                $button.click(function(e) { e.preventDefault(); });
                flow.assignBrowse($target[0], false, false, {
                    accept: accept
                });

                var $file = $target.find('input[type=file]');

                $file.attr('title', XF.htmlspecialchars(XF.phrase('attach')));
                $file.css('overflow', 'hidden');
                $file.css(XF.isRtl() ? 'right' : 'left', -1000);
            });
        },

        getFlowOptions: function() {
            return {
                target: this.options.uploadUrl,
                query: $.extend(this.attachmentManager.uploadQueryParams(), {
                    attachmentHash: this.options.attachmentHash,
                    contextData: JSON.stringify(this.options.contextData)
                }),
                chunkSize: this.options.chunkSize,
                maxFiles: 1,
                testChunks: false,
                forceChunkSize: true,
                simultaneousUploads: this.options.simultaneousUploads,
                allowDuplicateUploads: true,
                progressCallbacksInterval: 100,
                readFileFn: function (fileObj, startByte, endByte, fileType, chunk) {
                    var function_name = 'slice';

                    if (fileObj.file.slice) function_name =  'slice';
                    else if (fileObj.file.mozSlice) function_name = 'mozSlice';
                    else if (fileObj.file.webkitSlice) function_name = 'webkitSlice';

                    if (!fileType)
                    {
                        fileType = '';
                    }

                    chunk.readFinished(fileObj.file[function_name](startByte, endByte, fileType));
                }
            };
        },

        setupFlow: function()
        {
            var options = this.getFlowOptions(),
                flow = new Flow(options),
                self = this;

            if (!flow.support) {
                if (!window.FustyFlow)
                {
                    return null;
                }

                options.matchJSON = true;

                flow = new FustyFlow(options);
            }

            flow.on('fileAdded', XF.proxy(this, 'fileAdded'));
            flow.on('filesSubmitted', function() { self.attachmentManager.setUploading(true); flow.upload(); });
            flow.on('fileProgress', XF.proxy(this.attachmentManager, 'uploadProgress'));
            flow.on('fileSuccess', XF.proxy(this.attachmentManager, 'uploadSuccess'));
            flow.on('fileError', XF.proxy(this.attachmentManager, 'uploadError'));

            return flow;
        },

        fileAdded: function(file) {
            var $html = this.attachmentManager.applyUploadTemplate({
                filename: file.name,
                uploading: true
            });
            this.attachmentManager.resizeProgress($html, 0);

            $html.data('file', file);

            var $filesContainer = this.attachmentManager.$filesContainer;
            $filesContainer.addClass('is-active');
            $html.appendTo($filesContainer);

            this.attachmentManager.fileMap[file.uniqueIdentifier] = $html;
        },
    });

    XF.Element.register('tvu-video-upload', 'XF.Truonglv_VideoUpload');
}
(jQuery, this, document);