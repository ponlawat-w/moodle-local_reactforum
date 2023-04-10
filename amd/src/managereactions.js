import $ from 'jquery';

export const init = currentreactionsjsonstr => {
    $(() => {
        let $maindiv = $('div#fgroup_id_reactions');
        let $area = $maindiv.find('div.felement');
        let $filepicker = $('div#fitem_id_reactionimage');
        let $reactionallreplies = $('div#fitem_id_reactionallreplies');
        let $delayedcounter = $('div#fitem_id_delayedcounter');

        let editid = 0;
        const seteditid = x => { editid = x; };

        $filepicker.hide();

        let reactiontype = 'text';
        let reactions = [];

        const prepare_text_reactions = function () {
            if (reactiontype !== 'text') {
                return;
            }

            const $reactioninput = $('<input type="text">')
                .attr('class', 'reaction reaction-text form-control')
                .attr('reaction-id', '0')
                .attr('name', 'reactions[new][]');
            const $deletebtn = $('<button>')
                .attr('type', 'button')
                .attr('class', 'btn btn-danger')
                .html(M.str.local_reactforum.reactions_delete);
            const $reactioninputs_div = $('<div>').attr('class', 'reaction-input')
                .append($reactioninput)
                .append($deletebtn);

            const $inputcontainer = $('<div>').attr('id', 'reactions-container');

            const $addbtn = $('<button>')
                .attr('type', 'button')
                .attr('class', 'btn btn-primary')
                .html(M.str.local_reactforum.reactions_add);

            $addbtn.on('click', function () {
                const $newelement = $reactioninputs_div.clone(true, true);
                $newelement.find('input.reaction-text').val('');

                $inputcontainer.append($newelement);
                $newelement.find('input.reaction-text').trigger('focus');
            });

            $deletebtn.on('click', function () {
                const reaction_id = $(this).siblings('input.reaction-text').attr('reaction-id');

                if (reaction_id !== '0' && !confirm(M.str.local_reactforum.reactions_delete_confirmation)) {
                    return;
                }

                if (reaction_id !== '0') {
                    $(this).siblings('input.reaction-text')
                        .attr('type', 'hidden')
                        .attr('name', 'reactions[delete][]')
                        .val(reaction_id);

                    $(this).closest('div.reaction-input').hide();
                }
                else {
                    $(this).closest('div.reaction-input').remove();
                }
            });

            $area.html($inputcontainer)
                .append(
                    $('<div>').attr('class', 'container-fluid')
                        .css('padding', '0')
                        .html($addbtn)
                );

            if (reactions.length > 0) {
                $inputcontainer.html('');

                for (const reaction of reactions) {
                    const $newelement = $reactioninputs_div.clone(true, true);
                    $newelement.find('input.reaction-text')
                        .attr('reaction-id', reaction.id)
                        .val(reaction.value);

                    if (reaction.id === '0') {
                        $newelement.find('input.reaction-text').attr('name', 'reactions[new][]');
                    }
                    else {
                        $newelement.find('input.reaction-text')
                            .attr('name', '')
                            .on('change', function () {
                                $(this).attr('name', 'reactions[edit][' + $(this).attr('reaction-id') + ']');
                            });
                    }

                    $inputcontainer.append($newelement);
                }
            }
        };

        const prepare_image_reactions = function () {
            if (reactiontype !== 'image') {
                return;
            }

            $area.html('');

            const $input = $('input#id_reactionimage');
            const $temp_element = $input.prev().find('div.filepicker-filelist div.filepicker-filename');
            const temp_html = $temp_element.html();
            editid = 0;

            const $editheader = $('<h4/>');
            $editheader.html(M.str.local_reactforum.reactions_selectfile)
                .addClass('reaction-img-edit')
                .hide();

            const $cancelbtn = $('<button>');
            $cancelbtn.html(M.str.local_reactforum.reactions_cancel)
                .attr('type', 'button')
                .attr('class', 'reaction-img-edit btn btn-default')
                .css('margin', '0 5px')
                .on('click', function () {
                    editid = 0;
                    $editheader.hide();
                    $('.reaction-img-change-btn').prop('disabled', false);
                });

            $editheader.append($cancelbtn)
                .insertBefore($filepicker.find('input.fp-btn-choose'));

            // When new file uploaded
            $input.on('change', function () {
                const $filename = $(this).prev().find('div.filepicker-filename a');

                if (typeof $filename.attr('href') === 'undefined') {
                    return;
                }

                $.post(M.cfg.wwwroot + '/local/reactforum/imageuploaded.php',
                    {
                        'url': $filename.attr('href')
                    }, function (tempfileid) {
                        if (editid === 0)    // upload new reaction
                        {
                            const $newimg = $('<img/>');
                            $newimg.attr('alt', $filename.html())
                                .addClass('reaction-img')
                                .attr('src', $filename.attr('href'));

                            const $descriptioninput = $('<input>')
                                .attr('type', 'text')
                                .attr('placeholder', M.str.local_reactforum.description)
                                .attr('name', `reactions[desc][new][${tempfileid}]`)
                                .addClass('form-control');

                            const $deletebtn = $('<button>');
                            $deletebtn.html(M.str.local_reactforum.reactions_delete)
                                .attr('type', 'button')
                                .attr('class', 'btn btn-danger')
                                .on('click', function () {
                                    $(this).closest('div.reaction-item').remove();
                                });

                            const $hiddenelement = $('<input type="hidden" name="reactions[new][]"/>');
                            $hiddenelement.addClass('reaction')
                                .val(tempfileid);

                            const $reaction_div = $('<div/>');
                            $reaction_div.attr('class', 'reaction-item')
                                .append($newimg)
                                .append($descriptioninput)
                                .append($deletebtn)
                                .append($hiddenelement);

                            $area.append($reaction_div);
                        } else if (editid > 0) {
                            // upload new image for existing reaction
                            const $editdiv = $area.find('div#reaction-item-' + editid);
                            $editdiv.find('img.reaction-img')
                                .attr('src', $filename.attr('href'));

                            $area.find('input#reaction-image-edit-' + editid)
                                .val(tempfileid);

                            $editheader.hide();

                            editid = 0;
                            $('.reaction-img-change-btn').prop('disabled', false);
                        }
                    }, 'text');

                $temp_element.html(temp_html);
            });

            // Editing discussion
            for (const reaction of reactions) {
                const $img = $('<img/>');
                $img.attr('alt', reaction.id)
                    .attr('src', M.cfg.wwwroot + '/local/reactforum/image.php?id=' + reaction.id + '&sesskey=' + M.cfg.sesskey)
                    .addClass('reaction-img');

                const $descriptioninput = $('<input>')
                    .attr('type', 'text')
                    .attr('placeholder', M.str.local_reactforum.description)
                    .attr('name', `reactions[desc][edit][${reaction.id}]`)
                    .addClass('form-control')
                    .val(reaction.value);

                const $changebtn = $('<button>');
                $changebtn
                    .attr('type', 'button')
                    .attr('class', 'reaction-img-change-btn btn btn-outline-secondary')
                    .html(M.str.local_reactforum.reactions_changeimage)
                    .on('click', function () {
                        $('.reaction-img-change-btn').prop('disabled', false);
                        $(this).prop('disabled', true);

                        seteditid(reaction.id);
                        $editheader.show();
                    });

                const $deletebtn = $('<button>');
                $deletebtn.html(M.str.local_reactforum.reactions_delete)
                    .attr('type', 'button')
                    .attr('class', 'btn btn-danger')
                    .on('click', function () {
                        if (confirm(M.str.local_reactforum.reactions_delete_confirmation)) {
                            const $deletevalue = $('<input type="hidden"/>');
                            $deletevalue.attr('name', 'reactions[delete][]')
                                .val(reaction.id);

                            $area.append($deletevalue);

                            seteditid(0);
                            $('.reaction-img-change-btn').prop('disabled', false);

                            $(this).closest('div.reaction-item').remove();
                        }
                    });

                const $edit = $('<input type="hidden"/>');
                $edit.attr('name', 'reactions[edit][' + reaction.id + ']')
                    .attr('id', 'reaction-image-edit-' + reaction.id)
                    .addClass('reaction')
                    .val('0');

                const $reaction_div = $('<div/>');
                $reaction_div.addClass('reaction-item')
                    .attr('id', 'reaction-item-' + reaction.id)
                    .append($img)
                    .append($descriptioninput)
                    .append($changebtn)
                    .append($deletebtn)
                    .append($edit);

                $area.append($reaction_div);
            }

            $filepicker.show();
        };

        $("input[name='reactiontype']").on('change', function () {
            $filepicker.hide();

            if ($('input.reaction').length > 0 || reactiontype === 'discussion') {
                if (!confirm(M.str.local_reactforum.reactionstype_change_confirmation)) {
                    $(this).prop('checked', false);
                    $('input[name="reactiontype"][value="' + reactiontype + '"]').prop('checked', true);
                    if (reactiontype === 'image') {
                        $filepicker.show();
                    }
                    return false;
                }
            }

            if (
                $(this).val() !== 'text'
                && $(this).val() !== 'image'
                && $(this).val() !== 'none'
                && $(this).val() !== 'discussion'
            ) {
                return;
            }

            reactiontype = $(this).val();

            if (reactiontype === 'text') {
                reactions = [{id: '0', value: ''}];
                prepare_text_reactions();
            }
            else if (reactiontype === 'image') {
                reactions = [];
                prepare_image_reactions();
            }

            if (reactiontype === 'none' || reactiontype === 'discussion') {
                reactions = [];
                $maindiv.hide();
                $reactionallreplies.hide();
                $delayedcounter.hide();
            }
            else {
                $maindiv.show();
                $reactionallreplies.show();
                $delayedcounter.show();
            }
        });

        const currentreactions = JSON.parse(currentreactionsjsonstr);
        if (currentreactions) {
            reactions = currentreactions.reactions;
            reactiontype = currentreactions.type;

            $maindiv.hide();
            if (reactiontype === 'text') {
                $('input#id_reactiontype_text').prop('checked', true);
                prepare_text_reactions();
                $maindiv.show();
                $reactionallreplies.show();
                $delayedcounter.show();
            }
            else if (reactiontype === 'image') {
                $('input#id_reactiontype_image').prop('checked', true);
                prepare_image_reactions();
                $maindiv.show();
                $reactionallreplies.show();
                $delayedcounter.show();
            }
        }
        else {
            $maindiv.hide();
            $reactionallreplies.hide();
            $delayedcounter.hide();
        }
    });
};
