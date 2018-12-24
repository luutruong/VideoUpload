!function($, window, document, _undefined) {
    "use strict";

    XF.Truonglv_VideoUpload = XF.Element.newHandler({
        options: {
            attachmentHash: null,
            contextData: null,
            contentType: null,
            uploadUrl: null,
            chunkSize: 0,
            simultaneousUploads: 3,
            allowedExtensions: ''
        },

        flow: null,
        attachmentManager: null,
        $file: null,

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

            this.attachmentManager
                .$filesContainer
                .on('click', this.attachmentManager.options.actionButton, XF.proxy(this, 'actionButtonClick'));

            var flow = this.setupFlow();
            if (!flow) {
                console.error('No flow uploader support');
                return;
            }

            this.flow = flow;
            this.setupUploadButton(flow);

            this.disableUploadButton(false);
        },

        actionButtonClick: function(e) {
            e.preventDefault();

            var $target = $(e.currentTarget),
                action = $target.attr('data-action'),
                attachmentManager = this.attachmentManager,
                $row = $target.closest(attachmentManager.options.fileRow);
            if (action === 'tvu_video') {
                var bbCodeAttach = '[ATTACH=video]' + $row.data('attachment-id') + '[/ATTACH]';
                XF.insertIntoEditor(
                    attachmentManager.$target,
                    '<div>' + bbCodeAttach + '</div>',
                    bbCodeAttach,
                    '[data-attachment-target=false]'
                );
            }
        },

        disableUploadButton: function(disabled) {
            var method = disabled ? 'addClass' : 'removeClass';

            this.$target.prop('disabled', disabled)[method]('is-disabled');
            this.$file.prop('disabled', disabled);
        },

        setupUploadButton: function(flow) {
            var $button = this.$target,
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
            $file.attr('multiple', false);
            $file.css('overflow', 'hidden');
            $file.css(XF.isRtl() ? 'right' : 'left', -1000);

            this.$file = $file;
        },

        getFlowOptions: function() {
            return {
                target: this.options.uploadUrl,
                query: $.extend(this.attachmentManager.uploadQueryParams(), {
                    attachmentHash: this.options.attachmentHash,
                    contentType: this.options.contentType,
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

            flow.on('filesSubmitted', function() {
                self.attachmentManager.setUploading(true);
                flow.upload();
                self.disableUploadButton(true);
            });

            flow.on('fileProgress', XF.proxy(this.attachmentManager, 'uploadProgress'));
            flow.on('fileSuccess', XF.proxy(this, 'uploadSuccess'));
            flow.on('fileError', XF.proxy(this, 'uploadError'));

            return flow;
        },

        uploadSuccess: function(file, message, chunk) {
            var _this = this,
                onResponse,
                data;
            if (file.error) {
                this.disableUploadButton(false);

                return;
            }

            data = {
                flowTotalChunks: file.chunks.length,
                flowFilename: file.name,
                flowTotalSize: file.size,
                contextData: JSON.stringify(this.options.contextData),
                attachmentHash: this.options.attachmentHash,
                isCompleted: true
            };

            onResponse = function(data) {
                if (data.attachment) {
                    _this.attachmentManager.insertUploadedRow(
                        data.attachment,
                        _this.attachmentManager.fileMap[file.uniqueIdentifier]
                    );
                } else {
                    _this.attachmentManager.uploadSuccess(file, data, chunk);
                }
            };

            XF.ajax('POST', this.options.uploadUrl, data, onResponse)
                .always(function() {
                    _this.disableUploadButton(false);
                    _this.attachmentManager.setUploading(false);
                });
        },

        uploadError: function(file, message, chunk) {
            this.disableUploadButton(false);

            this.attachmentManager.uploadError(file, message, chunk);
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

            this.disableUploadButton(true);
        },
    });

    XF.Element.register('tvu-video-upload', 'XF.Truonglv_VideoUpload');
}
(jQuery, this, document);