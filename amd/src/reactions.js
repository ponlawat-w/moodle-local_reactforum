import $ from 'jquery';

export const init = (discussionid) => {
    const getreactionsdata = () => new Promise((resolve, reject) => {
        try {
            $.get(`${M.cfg.wwwroot}/local/reactforum/reactionsdata.php?id=${discussionid}`, response => {
                if (response && response.metadata) {
                    return resolve(response);
                }
                return resolve(null);
            });
        } catch (ex) {
            reject(ex);
        }
    });

    const reacted = (metadata, $button, $reactionsarea, postreactions) => {
        const $reactbuttons = $reactionsarea.find('.react-btn');
        for (const reactbutton of $reactbuttons) {
            const $reactbutton = $(reactbutton);
            const reactionid = $reactbutton.attr('data-reaction-id');
            if (!postreactions[reactionid]) {
                $reactbutton.prop('disabled', true);
            }
            applybutton(metadata, $reactbutton, postreactions[reactionid]);
        }
        $button.prop('disabled', false);
    };

    const react = (metadata, $button, $reactionsarea) => {
        const reactable = parseInt($button.attr('data-reactable'));
        if (!reactable) {
            return false;
        }
        const postid = $button.attr('data-post-id');
        const reactionid = $button.attr('data-reaction-id');
        $button.prop('disabled', true);
        try {
            $.post(`${M.cfg.wwwroot}/local/reactforum/react.php`, {
                post: postid,
                reaction: reactionid
            }, response => {
                $button.prop('disabled', false);
                if (response.error) {
                    throw response;
                }
                reacted(metadata, $button, $reactionsarea, response);
            });
        } catch (ex) {
            $button.prop('disabled', false);
            throw ex;
        }
    };

    const getcountertext = (reactiontype, count) => reactiontype === 'text' ? `- ${count}` : count;

    const applybutton = (metadata, $button, postreactiondata) => {
        $button.removeClass('btn-primary btn-outline-primary');
        $button.addClass(postreactiondata.reacted ? 'btn-primary' : 'btn-outline-primary');
        const $counter = $button.find('.reaction-counter');
        $counter.html(postreactiondata.count !== null ?
            getcountertext(metadata.reactiontype, postreactiondata.count.toString())
            : '');
        if (!parseInt(metadata.delayedcounter) || postreactiondata.count !== null) {
            $counter.show();
        } else {
            $counter.hide();
        }
        $button.attr('data-reactable', postreactiondata.enabled ? 1 : 0);
        $button.css('cursor', postreactiondata.enabled ? 'pointer' : 'default');
    };

    const addreactionbuttons = ($forumpost, reactionsdata) => {
        const postid = $forumpost.attr('data-post-id');
        const $appendedelement = $($forumpost.find('.post-content-container')[0]);
        const $reactionsarea = $('<div>');
        $reactionsarea.addClass('reactions-area');
        const postdata = reactionsdata.posts[postid];
        if (!postdata) {
            return;
        }
        for (const reaction of reactionsdata.reactions) {
            if (!postdata[reaction.id]) {
                continue;
            }
            const postreactiondata = postdata[reaction.id];
            const $button = $('<button>');
            $button.addClass('btn react-btn mx-1');
            $button.attr('data-post-id', postid)
                .attr('data-reaction-id', reaction.id);
            if (reactionsdata.metadata.reactiontype === 'text') {
                $button.html(reaction.reaction);
            } else if (reactionsdata.metadata.reactiontype === 'image') {
                $button.html($('<img>')
                    .addClass('reaction-img')
                    .attr('src', `${M.cfg.wwwroot}/local/reactforum/image.php?id=${reaction.id}`))
                    .attr('alt', reaction.reaction)
                    .attr('title', reaction.reaction);
            } else {
                continue;
            }
            const $counter = $('<span>');
            $counter.addClass('reaction-counter ml-1');
            $button.append($counter);
            $button.on('click', function() {
                react(reactionsdata.metadata, $(this), $reactionsarea);
            });
            applybutton(reactionsdata.metadata, $button, postreactiondata);
            $reactionsarea.append($button);
        }
        $appendedelement.append($reactionsarea);
    };

    const addmanagebutton = () => {
        const $firstpost = $('.firstpost[data-content="forum-post"]');
        if (!$firstpost.length) {
            return;
        }
        const $postactions = $firstpost.find('.post-actions');
        if (!$postactions.length) {
            return;
        }
        $postactions.append(
            $('<a>').attr('data-region', 'post-action')
                .attr('href', `${M.cfg.wwwroot}/local/reactforum/managereactions.php?d=${discussionid}`)
                .attr('role', 'menuitem')
                .addClass('btn btn-link')
                .html(M.str.local_reactforum.reactions)
        );
    };

    $(() => {
        const initialise = async() => {
            const reactionsdata = await getreactionsdata();
            if (!reactionsdata) {
                return;
            }
            if (reactionsdata.canmanage) {
                addmanagebutton();
            }
            const $forumposts = $('.forumpost');
            for (const forumpost of $forumposts) {
                addreactionbuttons($(forumpost), reactionsdata);
            }
        };

        initialise();
    });
};
