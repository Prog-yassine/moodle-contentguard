/* ContentGuard 0.2.0. Copyright 2026. GNU GPL v3 or later. */
(function() {
    'use strict';
    window.M = window.M || {};
    M.local_contentguard = M.local_contentguard || {};
    M.local_contentguard.init = function(Y, options) {
        // Moodle js_init_call supplies YUI as the first parameter.
        options = options || Y;
        if (!options || !options.selector) {
            return;
        }
        var initialise = function() {
            var roots = Array.prototype.slice.call(document.querySelectorAll(options.selector));
            if (!roots.length || roots[0].dataset.contentguardInitialised) {
                return;
            }
            var exemptSelector = 'input, textarea, select, option, button, a, [contenteditable]:not([contenteditable="false"]), ' +
                '[role="textbox"], [role="button"], audio, video, iframe, canvas';
            var isExempt = function(target) {
                var element = target && (target.nodeType === 1 ? target : target.parentElement);
                return !!(element && element.closest(exemptSelector));
            };
            var isProtected = function(target) {
                return !!target && roots.some(function(root) { return root.contains(target); });
            };
            var selectionIsProtected = function() {
                var selection = window.getSelection();
                if (!selection || !selection.rangeCount || selection.isCollapsed) {
                    return false;
                }
                if (isExempt(selection.anchorNode) || isExempt(selection.focusNode)) {
                    return false;
                }
                var range = selection.getRangeAt(0);
                return roots.some(function(root) { return range.intersectsNode(root); });
            };
            var notice = document.createElement('div');
            notice.className = 'contentguard-notice';
            notice.setAttribute('role', 'status');
            notice.setAttribute('aria-live', 'polite');
            notice.hidden = true;
            document.body.appendChild(notice);
            var noticeTimer;
            var block = function(event) {
                event.preventDefault();
                notice.textContent = options.blockedmessage;
                notice.hidden = false;
                window.clearTimeout(noticeTimer);
                noticeTimer = window.setTimeout(function() { notice.hidden = true; }, 2600);
            };
            var escapeXml = function(value) {
                return String(value).replace(/[<>&"']/g, function(character) {
                    return {'<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&apos;'}[character];
                });
            };
            roots.forEach(function(root) {
                root.dataset.contentguardInitialised = '1';
                root.classList.add('contentguard-protected');
                if (options.restrictcopy) {
                    root.classList.add('contentguard-no-select');
                }
                if (options.watermark) {
                    if (window.getComputedStyle(root).position === 'static') {
                        root.style.position = 'relative';
                    }
                    var overlay = document.createElement('div');
                    overlay.className = 'contentguard-watermark';
                    overlay.setAttribute('aria-hidden', 'true');
                    overlay.style.opacity = options.opacity;
                    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="340" height="190">' +
                        '<g transform="translate(170 95) rotate(-26)" text-anchor="middle" fill="#243d61" ' +
                        'font-family="Arial,sans-serif"><text font-size="18" font-weight="600">' +
                        escapeXml(options.watermarktext) + '</text><text y="23" font-size="12">' +
                        escapeXml(options.watermarkdate) + '</text></g></svg>';
                    overlay.style.backgroundImage = 'url("data:image/svg+xml,' + encodeURIComponent(svg) + '")';
                    root.appendChild(overlay);
                }
            });
            if (options.aiassessment) {
                roots.forEach(function(root) {
                    root.querySelectorAll('.que .formulation').forEach(function(question) {
                        if (question.dataset.contentguardAi === '1') {
                            return;
                        }
                        question.dataset.contentguardAi = '1';
                        question.classList.add('contentguard-ai-question');
                        var label = document.createElement('aside');
                        label.className = 'contentguard-ai-label';
                        var title = document.createElement('strong');
                        title.textContent = options.ailabel;
                        var instruction = document.createElement('span');
                        instruction.textContent = options.aiinstruction;
                        label.appendChild(title);
                        label.appendChild(instruction);
                        question.insertBefore(label, question.firstChild);
                        // Visible text for screenshots/OCR; this does not control external AI systems.
                        var aiOverlay = document.createElement('div');
                        aiOverlay.className = 'contentguard-ai-watermark';
                        aiOverlay.setAttribute('aria-hidden', 'true');
                        var aiSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="160">' +
                            '<g transform="translate(180 80) rotate(-19)" text-anchor="middle" fill="#663e50" ' +
                            'font-family="Arial,sans-serif"><text font-size="11" font-weight="600">' +
                            escapeXml(options.aitile) + '</text><text y="17" font-size="11">' +
                            escapeXml(options.aitileenglish) + '</text><text y="33" font-size="10">' +
                            escapeXml(options.watermarktext + ' · ' + options.watermarkdate) + '</text></g></svg>';
                        aiOverlay.style.backgroundImage = 'url("data:image/svg+xml,' + encodeURIComponent(aiSvg) + '")';
                        question.appendChild(aiOverlay);
                    });
                });
            }
            document.addEventListener('copy', function(event) {
                if (options.restrictcopy && !isExempt(event.target) &&
                    (isProtected(event.target) || selectionIsProtected())) {
                    block(event);
                }
            });
            document.addEventListener('contextmenu', function(event) {
                if (options.restrictcontextmenu && isProtected(event.target) && !isExempt(event.target)) {
                    block(event);
                }
            });
            document.addEventListener('keydown', function(event) {
                if (isExempt(event.target)) {
                    return;
                }
                var key = String(event.key).toLowerCase();
                if (options.restrictcopy && (event.ctrlKey || event.metaKey) && key === 'c' &&
                    (isProtected(event.target) || selectionIsProtected())) {
                    block(event);
                    return;
                }
                if (!options.restrictshortcuts) {
                    return;
                }
                var inspectShortcut = key === 'f12' ||
                    (event.ctrlKey && event.shiftKey && ['i', 'j', 'c'].indexOf(key) !== -1) ||
                    (event.metaKey && event.altKey && ['i', 'j', 'c'].indexOf(key) !== -1);
                // Keyboard interception is a courtesy restriction, never a developer-tools lock.
                var inReadingArea = isProtected(event.target) || event.target === document.body ||
                    event.target === document.documentElement;
                if (inspectShortcut && inReadingArea) {
                    block(event);
                }
            });
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initialise, {once: true});
        } else {
            initialise();
        }
    };
}());
