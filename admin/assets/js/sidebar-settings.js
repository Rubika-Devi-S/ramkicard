(() => {
    'use strict';

    let sidebarMenusTable = null;
    let sidebarMenus = [];

    const module =
        document.getElementById(
            'sidebarSettingsModule'
        );

    if (!module) {
        return;
    }

    const sidebarMenuModal =
        bootstrap.Modal.getOrCreateInstance(
            document.getElementById(
                'sidebarMenuModal'
            )
        );

    const csrfToken =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.content || '';

    if (csrfToken === '') {
        console.error(
            'Ramki Cards CSRF token meta tag is missing.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Always send both supported token field names
    |--------------------------------------------------------------------------
    | The API also receives X-CSRF-Token explicitly.
    |--------------------------------------------------------------------------
    */

    function sidebarRequest(data = {}) {
        const requestData = {
            ...data,
            _token: csrfToken,
            csrf_token: csrfToken,
        };

        return $.ajax({
            url: 'api/sidebar-menu.php',
            method: 'POST',
            dataType: 'json',
            data: requestData,
            headers: {
                'X-CSRF-Token': csrfToken,
            },
        });
    }

    function parentOptions(
        excludeId = 0,
        selectedId = 0
    ) {
        const options = sidebarMenus
            .filter(row =>
                Number(row.parent_id || 0) === 0
                && Number(row.id)
                    !== Number(excludeId)
            )
            .map(row => `
                <option
                    value="${Number(row.id)}"
                    ${
                        Number(selectedId)
                            === Number(row.id)
                            ? 'selected'
                            : ''
                    }
                >
                    ${RamkiAdmin.escape(
                        row.menu_name
                    )}
                </option>
            `)
            .join('');

        $('#sidebarParentId').html(
            '<option value="0">Main Menu</option>'
            + options
        );
    }

    function renderSidebarMenus(rows) {
        if (sidebarMenusTable) {
            sidebarMenusTable.destroy();
            sidebarMenusTable = null;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            $('#sidebarMenusTable tbody').html(`
                <tr>
                    <td
                        colspan="8"
                        class="text-center py-5 text-muted"
                    >
                        No sidebar menus found.
                    </td>
                </tr>
            `);

            return;
        }

        $('#sidebarMenusTable tbody').html(
            rows.map((row, index) => `
                <tr>
                    <td>${index + 1}</td>

                    <td>
                        <div
                            class="d-flex align-items-center gap-2"
                        >
                            <span
                                class="stat-icon"
                                style="
                                    width:38px;
                                    height:38px;
                                    font-size:15px;
                                "
                            >
                                <i class="${
                                    RamkiAdmin.escape(
                                        row.icon_class
                                        || 'fa-solid fa-circle'
                                    )
                                }"></i>
                            </span>

                            <div>
                                <strong>
                                    ${RamkiAdmin.escape(
                                        row.menu_name
                                    )}
                                </strong>

                                <small
                                    class="d-block text-muted"
                                >
                                    ${RamkiAdmin.escape(
                                        row.menu_key
                                    )}
                                </small>
                            </div>
                        </div>
                    </td>

                    <td>
                        ${RamkiAdmin.escape(
                            row.parent_name
                            || 'Main Menu'
                        )}
                    </td>

                    <td>
                        <code>
                            ${RamkiAdmin.escape(
                                row.route_name
                                || '#'
                            )}
                        </code>
                    </td>

                    <td>
                        ${Number(
                            row.sort_order
                            || 0
                        )}
                    </td>

                    <td>
                        <button
                            type="button"
                            class="btn btn-sm ${
                                Number(row.is_visible) === 1
                                    ? 'btn-success'
                                    : 'btn-outline-secondary'
                            } toggle-sidebar-menu"
                            data-id="${Number(row.id)}"
                            data-field="is_visible"
                        >
                            ${
                                Number(row.is_visible) === 1
                                    ? 'Shown'
                                    : 'Hidden'
                            }
                        </button>
                    </td>

                    <td>
                        <button
                            type="button"
                            class="btn btn-sm ${
                                row.status === 'active'
                                    ? 'btn-primary'
                                    : 'btn-outline-secondary'
                            } toggle-sidebar-menu"
                            data-id="${Number(row.id)}"
                            data-field="status"
                        >
                            ${
                                row.status === 'active'
                                    ? 'Active'
                                    : 'Inactive'
                            }
                        </button>
                    </td>

                    <td class="text-nowrap">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary edit-sidebar-menu"
                            data-id="${Number(row.id)}"
                        >
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger delete-sidebar-menu"
                            data-id="${Number(row.id)}"
                        >
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('')
        );

        sidebarMenusTable =
            new DataTable(
                '#sidebarMenusTable',
                {
                    pageLength: 20,
                    order: [[4, 'asc']],
                }
            );
    }

    function showRequestError(
        xhr,
        fallbackMessage
    ) {
        const message =
            xhr.responseJSON?.message
            || fallbackMessage;

        RamkiAdmin.toast(
            'error',
            message
        );
    }

    function loadSidebarMenus() {
        $('#sidebarMenusTable tbody').html(`
            <tr>
                <td
                    colspan="8"
                    class="text-center py-4 text-muted"
                >
                    Loading sidebar menus...
                </td>
            </tr>
        `);

        sidebarRequest({
            action: 'list',
        })
            .done(response => {
                if (!response.success) {
                    RamkiAdmin.toast(
                        'error',
                        response.message
                    );
                    return;
                }

                /*
                 * Supports both API response formats:
                 * data: [...]
                 * data: { rows: [...] }
                 */
                sidebarMenus =
                    Array.isArray(response.data)
                        ? response.data
                        : response.data?.rows || [];

                renderSidebarMenus(
                    sidebarMenus
                );
            })
            .fail(xhr => {
                const message =
                    xhr.responseJSON?.message
                    || 'Unable to load sidebar menus.';

                $('#sidebarMenusTable tbody').html(`
                    <tr>
                        <td
                            colspan="8"
                            class="text-center py-4 text-danger"
                        >
                            ${RamkiAdmin.escape(
                                message
                            )}
                        </td>
                    </tr>
                `);
            });
    }

    $('#refreshSidebarMenusButton')
        .on(
            'click',
            loadSidebarMenus
        );

    $('#addSidebarMenuButton')
        .on('click', () => {
            $('#sidebarMenuForm')[0].reset();

            $('#sidebarMenuId').val('');
            $('#sidebarRouteName').val('#');
            $('#sidebarIconClass').val(
                'fa-solid fa-circle'
            );
            $('#sidebarSortOrder').val('0');

            $(
                '#sidebarIsVisible, '
                + '#sidebarIsActive'
            ).prop('checked', true);

            parentOptions();

            $('#sidebarMenuModal .modal-title')
                .text('Add Sidebar Menu');

            sidebarMenuModal.show();
        });

    $(document).on(
        'click',
        '.edit-sidebar-menu',
        function () {
            const row =
                sidebarMenus.find(
                    item =>
                        Number(item.id)
                        === Number(
                            $(this).data('id')
                        )
                );

            if (!row) {
                return;
            }

            $('#sidebarMenuForm')[0].reset();

            $('#sidebarMenuId').val(row.id);
            $('#sidebarMenuName').val(
                row.menu_name
            );
            $('#sidebarMenuKey').val(
                row.menu_key
            );
            $('#sidebarRouteName').val(
                row.route_name || '#'
            );
            $('#sidebarIconClass').val(
                row.icon_class
                || 'fa-solid fa-circle'
            );
            $('#sidebarSortOrder').val(
                row.sort_order
            );
            $('#sidebarIsVisible').prop(
                'checked',
                Number(row.is_visible) === 1
            );
            $('#sidebarIsActive').prop(
                'checked',
                row.status === 'active'
            );

            parentOptions(
                row.id,
                row.parent_id || 0
            );

            $('#sidebarMenuModal .modal-title')
                .text('Edit Sidebar Menu');

            sidebarMenuModal.show();
        }
    );

    $('#sidebarMenuForm')
        .on(
            'submit',
            function (event) {
                event.preventDefault();

                const formData =
                    Object.fromEntries(
                        new FormData(
                            this
                        ).entries()
                    );

                /*
                 * Unchecked switches are not included
                 * by FormData, which is correct for
                 * the current PHP API.
                 */

                sidebarRequest(formData)
                    .done(response => {
                        if (!response.success) {
                            RamkiAdmin.toast(
                                'error',
                                response.message
                            );
                            return;
                        }

                        sidebarMenuModal.hide();

                        RamkiAdmin.toast(
                            'success',
                            response.message
                        );

                        loadSidebarMenus();
                    })
                    .fail(xhr =>
                        showRequestError(
                            xhr,
                            'Unable to save sidebar menu.'
                        )
                    );
            }
        );

    $(document).on(
        'click',
        '.toggle-sidebar-menu',
        function () {
            sidebarRequest({
                action: 'toggle',
                id: $(this).data('id'),
                field: $(this).data('field'),
            })
                .done(response => {
                    if (!response.success) {
                        RamkiAdmin.toast(
                            'error',
                            response.message
                        );
                        return;
                    }

                    RamkiAdmin.toast(
                        'success',
                        response.message
                    );

                    loadSidebarMenus();
                })
                .fail(xhr =>
                    showRequestError(
                        xhr,
                        'Unable to update sidebar menu.'
                    )
                );
        }
    );

    $(document).on(
        'click',
        '.delete-sidebar-menu',
        function () {
            const id =
                $(this).data('id');

            RamkiAdmin.confirm(
                'Delete this menu? Child menus must be moved or deleted first.',
                () => {
                    sidebarRequest({
                        action: 'delete',
                        id,
                    })
                        .done(response => {
                            if (!response.success) {
                                RamkiAdmin.toast(
                                    'error',
                                    response.message
                                );
                                return;
                            }

                            RamkiAdmin.toast(
                                'success',
                                response.message
                            );

                            loadSidebarMenus();
                        })
                        .fail(xhr =>
                            showRequestError(
                                xhr,
                                'Unable to delete sidebar menu.'
                            )
                        );
                },
                'Delete sidebar menu'
            );
        }
    );

    loadSidebarMenus();
})();
