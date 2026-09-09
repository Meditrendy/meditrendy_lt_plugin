(function () {
    'use strict';

    var documentLanguage = (document.documentElement.lang || '').toLowerCase();
    var isLatvianStorefront = documentLanguage === 'lv'
        || documentLanguage.indexOf('lv-') === 0
        || /(^|\.)meditrendy\.lv$/.test(window.location.hostname);

    if (!isLatvianStorefront) {
        return;
    }

    var translations = {
        'Add coupons': 'Pievienot kuponus',
        'Enter code': 'Ievadiet kodu'
    };

    function translateTextNode(node) {
        var text = node.nodeValue || '';
        var normalizedText = text.trim();

        if (!Object.prototype.hasOwnProperty.call(translations, normalizedText)) {
            return;
        }

        node.nodeValue = text.replace(normalizedText, translations[normalizedText]);
    }

    function translateAttributes(element) {
        ['aria-label', 'placeholder'].forEach(function (attribute) {
            var value = element.getAttribute(attribute);

            if (value && Object.prototype.hasOwnProperty.call(translations, value.trim())) {
                element.setAttribute(attribute, translations[value.trim()]);
            }
        });
    }

    function translateTree(root) {
        if (!root) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            translateTextNode(root);
            return;
        }

        if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_FRAGMENT_NODE) {
            return;
        }

        if (root.nodeType === Node.ELEMENT_NODE) {
            translateAttributes(root);
        }

        var walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT
        );

        while (walker.nextNode()) {
            if (walker.currentNode.nodeType === Node.TEXT_NODE) {
                translateTextNode(walker.currentNode);
            } else {
                translateAttributes(walker.currentNode);
            }
        }
    }

    function start() {
        translateTree(document.body);

        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'characterData') {
                    translateTextNode(mutation.target);
                    return;
                }

                mutation.addedNodes.forEach(translateTree);
            });
        }).observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
