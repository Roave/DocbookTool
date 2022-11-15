/**
 * This file is used by projects making use of the DocBookTool to provide consistent JS for navigating around the
 * documentation.
 *
 * Do NOT remove this file! Doing so will cause numerous downstream projects using this to no longer function correctly!
 */

"use strict";

/**
 * @param {string} title
 */
function loadDocBookNavigation(title) {
    /**
     * @param {NodeListOf<HTMLElement>} unselectedListElements
     * @param {HTMLElement} selectedListElement
     * @param {boolean} applySelectedClass
     * @param {boolean} applyHiddenClass
     */
    function selectFromList(unselectedListElements, selectedListElement, applySelectedClass, applyHiddenClass)
    {
        for (let i = 0; i < unselectedListElements.length; i++) {
            if (applySelectedClass && unselectedListElements[i].classList.contains('selected')) {
                unselectedListElements[i].classList.remove('selected');
            }
            if (applyHiddenClass && ! unselectedListElements[i].classList.contains('hidden')) {
                unselectedListElements[i].classList.add('hidden');
            }
        }

        if (applySelectedClass) {
            selectedListElement.classList.add('selected');
        }
        if (applyHiddenClass) {
            selectedListElement.classList.remove('hidden');
        }
    }

    function fragmentRoute(pathWithHash, pageTitle) {
        const path = pathWithHash.substring(1);

        const pathParts = path.split("/");

        if (pathParts.length >= 1) {
            const topTabLink = document.querySelector('#top-nav-tabs a[href="#' + pathParts[0] + '"]');
            if (topTabLink.parentElement) {
                selectFromList(document.querySelectorAll('ul#top-nav-tabs li'), topTabLink.parentElement, true, false);
            }
            selectFromList(document.querySelectorAll('section.tab-content'), document.getElementById(pathParts[0]), false, true);
            pageTitle = topTabLink.innerHTML + ' :: ' + pageTitle;
        }

        if (pathParts.length >= 2) {
            const docsSectionLink = document.querySelector('#docs-side-nav a[href="#docs/' + pathParts[1] + '"]');
            selectFromList(document.querySelectorAll('ul#docs-side-nav li'), docsSectionLink.parentElement, true, false);
            selectFromList(document.querySelectorAll('div#docs-content section'), document.getElementById('docs/' + pathParts[1]), false, true);
            pageTitle = docsSectionLink.innerHTML + ' :: ' + pageTitle;
            document.getElementById('docs-content').scrollTo(0, 0);
        }

        if (pathParts.length >= 3) {
            document.querySelectorAll('.selectedHeaderFromRoute').forEach(function (element) {
                element.classList.remove('selectedHeaderFromRoute');
            });
            const selectedHeader = document.querySelector('[id="' + path + '"]');
            selectedHeader.scrollIntoView();
            selectedHeader.classList.add('selectedHeaderFromRoute');
        }

        document.title = pageTitle;
        window.history.pushState(
            {},
            pageTitle,
            /([^#]+)#?.*/.exec(window.location.href)[1] + pathWithHash
        );
    }

    document.querySelectorAll('.fragmentRoute').forEach(function (e) {
        e.addEventListener('click',
            /**
             * @param {MouseEvent} clickEvent
             */
            function (clickEvent) {
                /** @type {EventTarget & HTMLAnchorElement} */
                const target = clickEvent.target;
                fragmentRoute(target.getAttribute('href'), title);
                clickEvent.stopPropagation();
                clickEvent.preventDefault();
            }
        );
    });

    // This JS is meant to be run in a single page context where navigation links to pages are actually
    // page jumps within a single HTML page.
    document.querySelectorAll('a:not(.fragmentRoute)').forEach(function (e) {
        e.addEventListener('click',
            /**
             * @param {MouseEvent} clickEvent
             */
            function (clickEvent) {
                let href = clickEvent.target.getAttribute('href');
                // If a protocol is set OR the URL is set to use whichever protocol is appropriate i.e. `//foo.com`
                let looksLikeAbsolutePath = (href) => href.indexOf('://') > 0 || href.indexOf('//') === 0;
                if (looksLikeAbsolutePath(href)) {
                    return;
                }

                // Right now this is assuming that documentation pages are written in Markdown
                let pageJump = `#docs/${href.replace('/', '_').replace('.md', '')}`;
                fragmentRoute(pageJump, title);
                clickEvent.stopPropagation();
                clickEvent.preventDefault();
            }
        );
    });

    document.querySelectorAll('h1[id],h2[id],h3[id],h4[id],h5[id],h6[id]').forEach(function (e) {
        let a = document.createElement('A');
        a.classList.add('permalink');
        a.href = '#' + e.id;
        a.innerHTML = '<i class="fas fa-link"></i>';
        a.title = 'Right click and copy to make a link to this section';
        a.addEventListener('click',
            /**
             * @param {MouseEvent} clickEvent
             */
            function (clickEvent) {
                /** @type {EventTarget & HTMLAnchorElement} */
                const target = clickEvent.target.parentElement;
                fragmentRoute(target.getAttribute('href'), title);
                clickEvent.stopPropagation();
                clickEvent.preventDefault();
            }
        );
        e.appendChild(a);
    });

    // // If we have a valid fragment in URL, select the appropriate tab, default to general docs
    setTimeout(() => {
        if (window.location.hash) {
            fragmentRoute(window.location.hash, title);
        } else {
            fragmentRoute('#docs/index', title);
        }
    }, 2000);

}
