/**
 * Renders and handles reaction buttons on a forum discussion page.
 *
 * @module      local_reactforum/reactions
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import $ from 'jquery';

/**
 * Initialises reactions on a discussion page.
 *
 * @param {number} discussionid
 */
export const init = (discussionid) => {
    /**
     * Fetches all reaction data for the discussion.
     *
     * @returns {Promise<object|null>}
     */
    const getreactionsdata = () =>
        Ajax.call([{
            methodname: 'local_reactforum_get_discussion_reactions',
            args: {discussionid},
        }])[0];

    /**
     * Updates all reaction button states after a react action.
     *
     * @param {object} metadata
     * @param {jQuery} $button — the button that was clicked
     * @param {jQuery} $reactionsarea — the container for reaction buttons on this post
     * @param {Array}  postreactions — array of {reactionid, reacted, count, enabled} from WS
     */
    const reacted = (metadata, $button, $reactionsarea, postreactions) => {
        const $reactbuttons = $reactionsarea.find('.react-btn');
        // Build a map for O(1) lookup.
        const statebyid = {};
        for (const state of postreactions) {
            statebyid[state.reactionid] = state;
        }
        for (const reactbutton of $reactbuttons) {
            const $reactbutton = $(reactbutton);
            const reactionid = parseInt($reactbutton.attr('data-reaction-id'));
            const state = statebyid[reactionid];
            if (!state) {
                $reactbutton.prop('disabled', true);
            } else {
                applybutton(metadata, $reactbutton, state);
            }
        }
        $button.prop('disabled', false);
    };

    /**
     * Sends a react request for the given button.
     *
     * @param {object} metadata
     * @param {jQuery} $button
     * @param {jQuery} $reactionsarea
     */
    const react = (metadata, $button, $reactionsarea) => {
        const reactable = parseInt($button.attr('data-reactable'));
        if (!reactable) {
            return;
        }
        const postid = parseInt($button.attr('data-post-id'));
        const reactionid = parseInt($button.attr('data-reaction-id'));
        $button.prop('disabled', true);
        Ajax.call([{
            methodname: 'local_reactforum_react',
            args: {postid, reactionid},
        }])[0]
        .then(response => {
            reacted(metadata, $button, $reactionsarea, response);
            return;
        })
        .catch(() => {
            $button.prop('disabled', false);
        });
    };

    /**
     * Returns the display text for a reaction counter.
     *
     * @param {string} reactiontype
     * @param {number} count
     * @returns {string}
     */
    const getcountertext = (reactiontype, count) => reactiontype === 'text' ? `- ${count}` : String(count);

    /**
     * Applies the given reaction state to a button element.
     *
     * @param {object} metadata
     * @param {jQuery} $button
     * @param {object} state — {reacted, count, enabled}
     */
    const applybutton = (metadata, $button, state) => {
        $button.removeClass('btn-primary btn-outline-primary');
        $button.addClass(state.reacted ? 'btn-primary' : 'btn-outline-primary');
        const $counter = $button.find('.reaction-counter');
        $counter.html(state.count !== null && state.count !== undefined
            ? getcountertext(metadata.reactiontype, state.count)
            : '');
        if (!parseInt(metadata.delayedcounter) || (state.count !== null && state.count !== undefined)) {
            $counter.show();
        } else {
            $counter.hide();
        }
        $button.attr('data-reactable', state.enabled ? 1 : 0);
        $button.css('cursor', state.enabled ? 'pointer' : 'default');
    };

    /**
     * Builds and appends reaction buttons to a forum post element.
     *
     * @param {jQuery} $forumpost
     * @param {object} reactionsdata — full WS response
     */
    const addreactionbuttons = ($forumpost, reactionsdata) => {
        const postid = parseInt($forumpost.attr('data-post-id'));
        const $appendedelement = $($forumpost.find('.post-content-container')[0]);
        const $reactionsarea = $('<div>').addClass('reactions-area');

        // Find the post data by postid.
        const postentry = reactionsdata.posts.find(p => p.postid === postid);
        if (!postentry) {
            return;
        }
        // Build a state map keyed by reactionid.
        const statemap = {};
        for (const state of postentry.reactions) {
            statemap[state.reactionid] = state;
        }

        for (const reaction of reactionsdata.reactions) {
            const state = statemap[reaction.id];
            if (!state) {
                continue;
            }
            const $button = $('<button>').addClass('btn react-btn mx-1');
            $button.attr('data-post-id', postid)
                   .attr('data-reaction-id', reaction.id);

            if (reactionsdata.metadata.reactiontype === 'text') {
                $button.html(reaction.reaction);
            } else if (reactionsdata.metadata.reactiontype === 'image') {
                $button.html(
                    $('<img>').addClass('reaction-img')
                              .attr('src', `${M.cfg.wwwroot}/local/reactforum/image.php?id=${reaction.id}`)
                              .attr('alt', reaction.reaction)
                              .attr('title', reaction.reaction)
                );
            } else {
                continue;
            }

            const $counter = $('<span>').addClass('reaction-counter ml-1');
            $button.append($counter);
            $button.on('click', function() {
                react(reactionsdata.metadata, $(this), $reactionsarea);
            });
            applybutton(reactionsdata.metadata, $button, state);
            $reactionsarea.append($button);
        }
        $appendedelement.append($reactionsarea);
    };

    /**
     * Adds a "Reactions" management button to the first post's action menu.
     */
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
