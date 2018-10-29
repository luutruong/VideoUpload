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

            this.resumable = new Resumable({
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
                simultaneousUploads: $button.data('simultaneousuploads') || 3
            });

            this.resumable.assignBrowse(this.$button);

            this.resumable.on('fileAdded', $.context(this, 'onFileAdded'));
            this.resumable.on('fileSuccess', $.context(this, 'onFileSuccess'));
            this.resumable.on('fileError', $.context(this, 'onFileError'));
            this.resumable.on('fileProgress', $.context(this, 'onFileProgress'));
        },
        
        onFileAdded: function (file) {
            var fileExt = file.fileName.substr(file.fileName.lastIndexOf('.') + 1).toLowerCase();
            
            if (this.allowExtensions.indexOf(fileExt) === -1) {
                XenForo.alert(this.$button.data('error'));

                this.resumable.removeFile(file);

                return;
            }

            this.disableUpload(true);

            var event = $.Event('AttachmentQueued');
            event.file = file.file;
            event.isImage = false;
            this.$attachUploader.trigger(event);

            // begin upload file.
            this.resumable.upload();
        },

        onFileProgress: function (file, message) {
            if (message === undefined) {
                return;
            }

            this.$attachUploader.trigger({
                type: 'AttachmentUploadProgress',
                file: file.file,
                bytes: file.progress() * file.file.size
            });
        },
        
        onFileSuccess: function (file, message) {
            var json;
            try {
                json = JSON.parse(message);
            } catch (e) {
            }

            if (json && json.attachment_id) {
                this.$attachUploader.trigger({
                    type: 'AttachmentUploaded',
                    file: file.file,
                    ajaxData: json
                });
            } else {
                this.$attachUploader.trigger({
                    type: 'AttachmentUploadError',
                    file: file.file,
                    message: this.$button.data('file-error'),
                    ajaxData: { error: [ this.$button.data('file-error') ]}
                });

                this.resumable.removeFile(file);
            }
            
            this.disableUpload(false);
        },
        
        onFileError: function (file, message) {
            var jsonError, _this = this;
            try {
                jsonError = JSON.parse(message);
            } catch (e) {
            }

            this.$attachUploader.trigger({
                type: 'AttachmentUploadError',
                file: file.file,
                message: this.$button.data('file-error'),
                ajaxData: jsonError || { error: [ this.$button.data('file-error') ]}
            });

            this.resumable.removeFile(file);
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