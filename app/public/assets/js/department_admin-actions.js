(function () {
    'use strict';

    function toast(message, ok) {
        var box = document.getElementById('admin-toast');
        if (!box) {
            box = document.createElement('div');
            box.id = 'admin-toast';
            document.body.appendChild(box);
        }
        // Reuse the app's flash styling (.alert + variant); .admin-toast only
        // adds the floating position and the show/hide transition.
        box.className = 'admin-toast alert ' + (ok ? 'alert-success' : 'alert-error');
        box.textContent = message;
        box.classList.add('is-visible');
        clearTimeout(box._timer);
        box._timer = setTimeout(function () { box.classList.remove('is-visible'); }, 2600);
    }

    /**
     * Replaces a member <tr> with the server-rendered one (same markup as the
     * initial page: icons, badge, fresh enabled button), then re-applies the
     * table sort so a status change can move the row into place.
     */
    function replaceMemberRow(oldRow, html) {
        if (!html) {
            return;
        }
        var tmp = document.createElement('tbody');
        tmp.innerHTML = html.trim();
        var newRow = tmp.firstElementChild;
        if (!newRow) {
            return;
        }
        oldRow.parentNode.replaceChild(newRow, oldRow);
        var table = newRow.closest('table.sortable');
        if (table && typeof table._reapplySort === 'function') {
            table._reapplySort();
        }
    }

    function resortTable(table) {
        if (table && typeof table._reapplySort === 'function') {
            table._reapplySort();
        }
    }

    /** The sortable table holding a key's rows (researcher or generic list), if any. */
    function listTable(key) {
        return document.querySelector('[data-researcher-table="' + key + '"]')
            || document.querySelector('[data-list-table="' + key + '"]');
    }

    /**
     * Each movable list is one "key". A table key holds <tr> rows in a sortable
     * table; a "pending" key holds <details> blocks in a plain container.
     * Returns the container element rows live in.
     */
    function listBody(key) {
        var table = listTable(key);
        if (table) {
            return table.tBodies[0];
        }
        return document.querySelector('[data-pending-list="' + key + '"]'); // a pending key
    }

    function countRows(key) {
        var table = listTable(key);
        if (table) {
            return table.tBodies[0].rows.length;
        }
        var list = document.querySelector('[data-pending-list="' + key + '"]');
        return list ? list.querySelectorAll('.admin-pending-row').length : 0;
    }

    /** Hides a [data-hide-when-empty] block when its list has no rows. */
    function toggleSectionVisibility(key) {
        var section = document.querySelector('[data-hide-when-empty="' + key + '"]');
        if (section) {
            section.hidden = countRows(key) === 0;
        }
    }

    /**
     * Shows a [data-empty-any="k1 k2"] message only when every listed key is
     * empty (e.g. the "no requests at all" line spanning both pending lists).
     */
    function refreshEmptyAny(key) {
        var msgs = document.querySelectorAll('[data-empty-any~="' + key + '"]');
        msgs.forEach(function (msg) {
            var keys = msg.getAttribute('data-empty-any').split(/\s+/);
            var allEmpty = keys.every(function (k) { return countRows(k) === 0; });
            msg.hidden = !allEmpty;
        });
    }

    /** Refreshes the "(n)" count and the empty-state message for a list key. */
    function refreshListState(key) {
        if (!key) {
            return;
        }
        var count = document.querySelector('[data-count="' + key + '"]');
        var empty = document.querySelector('[data-empty="' + key + '"]');
        var n = countRows(key);
        if (count) {
            count.textContent = String(n);
        }
        if (empty) {
            empty.hidden = n !== 0;
        }
        toggleSectionVisibility(key);
        refreshEmptyAny(key);
        resortTable(listTable(key));
    }

    /**
     * Moves a request/researcher between lists: drops the source element,
     * inserts the server-rendered element into the target list (when there is
     * one -- reject has no target), then refreshes counts and empty states.
     * The source may be a <tr> or a <details>; the target an empty cell anchor.
     */
    function applyMoveRow(sourceEl, sourceKey, target, html) {
        sourceEl.remove();

        if (target && html) {
            var body = listBody(target);
            if (body) {
                var tmp = document.createElement(target === 'pending' ? 'div' : 'tbody');
                tmp.innerHTML = html.trim();
                var newEl = tmp.firstElementChild;
                if (newEl) {
                    body.appendChild(newEl);
                }
            }
        }

        refreshListState(sourceKey);
        refreshListState(target);
    }

    /**
     * Locates the element a form acts on and its list key. A pending request is
     * a <details>; a researcher is a <tr> in a table whose key is on the table.
     */
    function sourceOf(form) {
        var details = form.closest('.admin-pending-row');
        if (details) {
            var list = details.closest('[data-pending-list]');
            return { el: details, key: list ? list.getAttribute('data-pending-list') : 'pending' };
        }
        var tr = form.closest('tr');
        var table = tr ? tr.closest('[data-researcher-table], [data-list-table]') : null;
        var key = table
            ? (table.getAttribute('data-researcher-table') || table.getAttribute('data-list-table'))
            : null;
        return { el: tr, key: key };
    }

    /** Opens the shared modal filled with a member row's info template. */
    function openMemberModal(trigger) {
        var modal = document.getElementById('member-modal');
        var body = document.getElementById('member-modal-body');
        var title = document.getElementById('member-modal-title');
        var tpl = trigger.parentNode.querySelector('[data-member-info-panel]');
        if (!modal || !body || !tpl) {
            return;
        }
        body.innerHTML = '';
        body.appendChild(tpl.content.cloneNode(true));
        if (title) {
            title.textContent = trigger.getAttribute('data-member-name') || 'Informations';
        }
        modal.style.display = 'flex';
    }

    function closeMemberModal() {
        var modal = document.getElementById('member-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function handle(form, e) {
        e.preventDefault();

        var confirmMsg = form.getAttribute('data-confirm');
        if (confirmMsg && !window.confirm(confirmMsg)) {
            return;
        }

        var action = form.getAttribute('data-ajax-action');
        // The set-active form may live in the shared modal (cloned out of the
        // row), so fall back to locating the original <tr> by user id.
        var row = form.closest('tr');
        if (!row && action === 'set-active') {
            var uid = form.querySelector('[name="user_id"]');
            row = uid ? document.querySelector('tr[data-user-id="' + uid.value + '"]') : null;
        }
        var source = sourceOf(form);
        var button = form.querySelector('button');
        if (button) {
            button.disabled = true;
        }

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
            .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
            .then(function (r) {
                toast(r.data.message, r.ok);
                if (!r.ok) {
                    if (button) { button.disabled = false; }
                    return;
                }
                if (action === 'set-active') {
                    closeMemberModal();
                    replaceMemberRow(row, r.data.row);
                } else if (action === 'move-row') {
                    applyMoveRow(source.el, source.key, r.data.target, r.data.row);
                }
            })
            .catch(function () {
                toast('Une erreur est survenue, merci de reessayer.', false);
                if (button) { button.disabled = false; }
            });
    }

    function init() {
        document.addEventListener('submit', function (e) {
            var form = e.target.closest('form[data-ajax-action]');
            if (form) {
                handle(form, e);
            }
        });

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-member-info]');
            if (trigger) {
                openMemberModal(trigger);
                return;
            }
            // Close on the close button or a click on the backdrop itself.
            if (e.target.closest('#member-modal-close') || e.target.id === 'member-modal') {
                closeMemberModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMemberModal();
            }
        });

        var loadMoreBtn = document.getElementById('load-more-btn');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function () {
                var btn = this;
                var id = btn.getAttribute('data-next-id');
                var lastName = btn.getAttribute('data-next-lastname');
                var firstName = btn.getAttribute('data-next-firstname');

                if (!id) return;

                btn.disabled = true;
                btn.textContent = 'Chargement...';

                var url = '/department-admin/users?' + new URLSearchParams({
                    'c_id': id,
                    'c_last_name': lastName,
                    'c_first_name': firstName
                });

                fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) { 
                    if (!res.ok) throw new Error();
                    return res.json(); 
                })
                .then(function (res) {
                    if (res.success) {
                        if (res.html && res.html.trim() !== '') {
                            var tbody = document.getElementById('users-table-body');
                            var tmp = document.createElement('tbody');
                            tmp.innerHTML = res.html;
                            while (tmp.firstChild) {
                                tbody.appendChild(tmp.firstChild);
                            }

                            var table = tbody.closest('table.sortable');
                            if (table && typeof table._reapplySort === 'function') {
                                table._reapplySort();
                            }
                        }

                        if (res.nextCursor) {
                            btn.setAttribute('data-next-id', res.nextCursor.id);
                            btn.setAttribute('data-next-lastname', res.nextCursor.last_name);
                            btn.setAttribute('data-next-firstname', res.nextCursor.first_name);
                            btn.disabled = false;
                            btn.textContent = "Charger plus d'utilisateurs";
                        } else {
                            btn.style.display = 'none';
                        }
                    }
                })
                .catch(function () {
                    toast('Impossible de charger les utilisateurs suivants.', false);
                    btn.disabled = false;
                    btn.textContent = "Charger plus d'utilisateurs";
                });
            });
        }
        var searchInput = document.getElementById('user-search-input');
        var searchTimer = null;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var val = this.value;
                if (val.length > 0) {
                    this.value = val.charAt(0).toUpperCase() + val.slice(1);
                }

                var query = this.value.trim();
                
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    var tbody = document.getElementById('users-table-body');
                    if (!tbody) return;

                    var url = query === '' 
                        ? '/department-admin/users' 
                        : '/department-admin/search?q=' + encodeURIComponent(query);

                    fetch(url, {
                        method: 'GET',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (res) {
                        if (!res.ok) throw new Error();
                        return res.json();
                    })
                    .then(function (res) {
                        console.log(res);
                        if (res.success) {
                            tbody.innerHTML = res.html;

                            if (loadMoreBtn) {
                                if (res.nextCursor) {
                                    loadMoreBtn.setAttribute('data-next-id', res.nextCursor.id);
                                    loadMoreBtn.setAttribute('data-next-lastname', res.nextCursor.last_name);
                                    loadMoreBtn.setAttribute('data-next-firstname', res.nextCursor.first_name);
                                    loadMoreBtn.style.display = 'inline-block';
                                    loadMoreBtn.disabled = false;
                                } else {
                                    loadMoreBtn.style.display = 'none';
                                }
                            }

                            var table = tbody.closest('table.sortable');
                            if (table && typeof table._reapplySort === 'function') {
                                table._reapplySort();
                            }
                        }
                    })
                    .catch(function () {
                        toast('Erreur lors de la recherche.', false);
                    });
                }, 300);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();