!function ($, window, document) {
    XenForo.TVU_VideoUploadButton = function ($button) {
        this.__construct($button); };
    XenForo.TVU_VideoUploadButton.prototype = {
        __construct: function ($button) {
            this.$button = $button;
            this.$attachUploader = $('.AttachmentUploader');

            var extensions = $button.data('extensions').toLowerCase().split(',');
            this.allowExtensions = [];
            for (var i = 0; i < extensions.length; i++) {
                this.allowExtensions.push($.trim(extensions[i]));
            }

            this.config = {
                target: 'misc/tvu-video-upload',
                query: {
                    _xfToken: XenForo._csrfToken,
                    _xfResponseType: 'json',
                    content_data: JSON.stringify($button.data('payload')),
                    hash: $button.data('hash')
                },
                chunkSize: $button.data('chunk'),
                maxFiles: 1,
                testChunks: false,
                forceChunkSize: true,
                simultaneousUploads: $button.data('simultaneousuploads') || 3,
                readFileFn: function (fileObj, startByte, endByte, fileType, chunk) {
                    var function_name = 'slice';

                    if (fileObj.file.slice) {
                        function_name =  'slice';
                    } else if (fileObj.file.mozSlice) {
                        function_name = 'mozSlice';
                    } else if (fileObj.file.webkitSlice) {
                        function_name = 'webkitSlice';
                    }

                    if (!fileType) {
                        fileType = '';
                    }

                    chunk.readFinished(fileObj.file[function_name](startByte, endByte, fileType));
                },
                allowDuplicateUploads: true
            };
            this.flow = new Flow(this.config);

            this.flow.assignBrowse(this.$button, false, false, {
                accept: '.' + $button.data('extensions').toLowerCase().replace(/,/g, ',.')
            });

            this.flow.on('fileAdded', $.context(this, 'onFileAdded'));
            this.flow.on('fileError', $.context(this, 'onFileError'));
            this.flow.on('fileProgress', $.context(this, 'onFileProgress'));
            this.flow.on('complete', $.context(this, 'onComplete'));
            this.flow.on('filesSubmitted', $.context(this, 'onFilesSubmitted'));

            this._file = null;
        },

        onFilesSubmitted: function () {
            this.flow.upload();
        },

        onComplete: function () {
            if (this._file === null
                || this._file._prevUploadedSize !== this._file.size
            ) {
                this.disableUpload(false);

                return;
            }

            var _this = this, file = this._file, data;
            data = {
                flowTotalChunks: file.chunks.length,
                flowFilename: file.name,
                flowTotalSize: file.size,
                is_completed: 1,
                content_data: this.config.query.content_data,
                hash: this.config.query.hash
            };

            XenForo.ajax(this.config.target, data, function (ajaxData) {
                if (XenForo.hasResponseError(ajaxData)) {
                    _this.$attachUploader.trigger({
                        type: 'AttachmentUploadError',
                        file: file,
                        message: _this.$button.data('file-error'),
                        ajaxData: { error: [ _this.$button.data('file-error') ]},
                        flow: _this.flow
                    });

                    return;
                }

                _this.$attachUploader.trigger({
                    type: 'AttachmentUploaded',
                    file: file,
                    ajaxData: ajaxData,
                    flow: _this.flow
                });
            }).always(function () {
                _this.disableUpload(false);
                _this.flow.removeFile(_this._file);
                _this._file = null;
            });
        },

        onFileAdded: function (file) {
            this.disableUpload(true);

            this._file = file;

            var event = $.Event('AttachmentQueued');
            event.file = file;
            event.flow = this.flow;
            event.isImage = false;
            this.$attachUploader.trigger(event);
        },

        onFileProgress: function (file) {
            file.id = file.uniqueIdentifier;

            this.$attachUploader.trigger({
                type: 'AttachmentUploadProgress',
                file: file,
                bytes: Math.round(file.progress() * file.size),
                flow: this.flow
            });
        },
        
        onFileError: function (file, message) {
            this.$attachUploader.trigger({
                type: 'AttachmentUploadError',
                file: file,
                message: message,
                ajaxData: { error: [ this.$button.data('file-error') ]},
                flow: this.flow
            });

            // this.removeFile(file);

            setTimeout(function () {
                _this.disableUpload(false); }, 1000);
        },

        disableUpload: function (disabled) {
            var method = disabled ? 'addClass' : 'removeClass';

            this.$button.prop('disabled', disabled)[method]('disabled');
        }
    };

    XenForo.TVU_AttachmentInserter = function ($trigger) {
        $trigger.click(function (e) {
            e.preventDefault();

            var attachmentId = $trigger.data('attachmentid'),
                editor,
                bbcode;

            bbcode = '[ATTACH=video]' + attachmentId + '[/ATTACH]';
            editor = XenForo.getEditorInForm($trigger.closest('form'), ':not(.NoAttachment)');

            if (editor) {
                if (editor.$editor) {
                    editor.insertHtml(bbcode);
                    var update = editor.$editor.data('xenForoElastic');
                    if (update) {
                        setTimeout(function () {
                            update(); }, 250);
                        setTimeout(function () {
                            update(); }, 1000);
                    }
                } else {
                    editor.val(editor.val() + bbcode);
                }
            }
        });
    };

    XenForo.TVU_VideoSetup = function ($element) {
        videojs($element.attr('id'), {
            fluid: true
        });
    };

    XenForo.register('.TVU_VideoUploadButton', 'XenForo.TVU_VideoUploadButton');
    XenForo.register('.TVU_AttachmentInserter', 'XenForo.TVU_AttachmentInserter');
    XenForo.register('.TVU_VideoSetup', 'XenForo.TVU_VideoSetup');
}
(jQuery, window, document);