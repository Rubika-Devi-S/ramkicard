(() => {
    'use strict';

    const module = document.getElementById(
        'websiteContentModule'
    );

    if (!module) {
        return;
    }

    const csrfToken = document.querySelector(
        'meta[name="csrf-token"]'
    )?.content || '';

    const itemModalElement = document.getElementById(
        'contentItemModal'
    );

    const itemModal = itemModalElement
        ? bootstrap.Modal.getOrCreateInstance(
            itemModalElement
        )
        : null;

    const itemForm = document.getElementById(
        'contentItemForm'
    );

    function toast(type, message) {
        if (
            window.RamkiAdmin
            && typeof RamkiAdmin.toast === 'function'
        ) {
            RamkiAdmin.toast(type, message);
            return;
        }

        window.alert(message);
    }

    function parseApiResponse(
        rawResponse,
        statusCode
    ) {
        const cleanResponse = String(
            rawResponse || ''
        )
            .replace(/^\uFEFF/, '')
            .trim();

        if (cleanResponse === '') {
            throw new Error(
                `The website-content API returned an empty response (HTTP ${statusCode}).`
            );
        }

        try {
            return JSON.parse(cleanResponse);
        } catch (firstError) {
            /*
             * A PHP notice or warning may have been printed before the
             * JSON response. Extract the JSON object when possible.
             */
            const firstBrace =
                cleanResponse.indexOf('{');

            const lastBrace =
                cleanResponse.lastIndexOf('}');

            if (
                firstBrace >= 0
                && lastBrace > firstBrace
            ) {
                const jsonPart =
                    cleanResponse.slice(
                        firstBrace,
                        lastBrace + 1
                    );

                try {
                    return JSON.parse(jsonPart);
                } catch (secondError) {
                    // Fall through to the detailed error below.
                }
            }

            const responsePreview =
                cleanResponse
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .slice(0, 500);

            throw new Error(
                responsePreview
                || `The website-content API returned an invalid response (HTTP ${statusCode}).`
            );
        }
    }

    async function request(
        formData,
        endpoint = 'api/website-content.php'
    ) {
        formData.set('_token', csrfToken);
        formData.set('csrf_token', csrfToken);

        const response = await fetch(
            endpoint,
            {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'follow',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With':
                        'XMLHttpRequest',
                },
                body: formData,
            }
        );

        const rawResponse =
            await response.text();

        const result = parseApiResponse(
            rawResponse,
            response.status
        );

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to save the website content.'
            );
        }

        return result;
    }

    async function submitForm(form, button) {
        const originalHtml = button?.innerHTML || '';

        if (button) {
            button.disabled = true;
            button.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';
        }

        try {
            const result = await request(
                new FormData(form),
                form.getAttribute('action')
                    || 'api/website-content.php'
            );

            toast('success', result.message);

            window.setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set(
                    'content_revision',
                    String(Date.now())
                );
                window.location.href = url.toString();
            }, 450);
        } catch (error) {
            toast('error', error.message);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    }

    document.getElementById(
        'websiteGeneralSettingsForm'
    )?.addEventListener('submit', event => {
        event.preventDefault();

        if (!event.currentTarget.reportValidity()) {
            return;
        }

        submitForm(
            event.currentTarget,
            event.submitter
        );
    });

    document.querySelectorAll(
        '.website-section-form'
    ).forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            submitForm(form, event.submitter);
        });
    });

    function setItemLabels(labels = {}) {
        document.getElementById(
            'contentItemTitleLabel'
        ).textContent =
            `${labels.title || 'Item Title'} *`;

        document.getElementById(
            'contentItemSubtitleLabel'
        ).textContent =
            labels.subtitle || 'Subtitle';

        document.getElementById(
            'contentItemContentLabel'
        ).textContent =
            labels.content || 'Description';

        document.getElementById(
            'contentItemIconLabel'
        ).textContent =
            labels.icon || 'Emoji / Icon';
    }

    function prepareItemForm({
        sectionKey,
        sectionLabel,
        labels,
        hasRating,
        item = null,
    }) {
        if (!itemForm || !itemModal) {
            return;
        }

        itemForm.reset();

        itemForm.elements.id.value =
            item?.id || 0;

        itemForm.elements.section_key.value =
            sectionKey;

        itemForm.elements.item_title.value =
            item?.item_title || '';

        itemForm.elements.item_subtitle.value =
            item?.item_subtitle || '';

        itemForm.elements.item_content.value =
            item?.item_content || '';

        itemForm.elements.icon_class.value =
            item?.icon_class || '';

        itemForm.elements.link_text.value =
            item?.link_text || '';

        itemForm.elements.link_url.value =
            item?.link_url || '';

        itemForm.elements.sort_order.value =
            item?.sort_order ?? 0;

        itemForm.elements.status.value =
            item?.status || 'active';

        itemForm.elements.rating.value =
            item?.rating || 5;

        document.getElementById(
            'contentItemSectionLabel'
        ).textContent = sectionLabel;

        document.querySelector(
            '#contentItemModal .modal-title'
        ).textContent = item?.id
            ? 'Edit Website Item'
            : 'Add Website Item';

        const ratingField = document.querySelector(
            '.content-item-rating-field'
        );

        if (ratingField) {
            ratingField.hidden = !hasRating;
        }

        setItemLabels(labels);
        itemModal.show();
    }

    document.querySelectorAll(
        '.add-content-item'
    ).forEach(button => {
        button.addEventListener('click', () => {
            prepareItemForm({
                sectionKey:
                    button.dataset.sectionKey,
                sectionLabel:
                    button.dataset.sectionLabel,
                labels: JSON.parse(
                    button.dataset.labels || '{}'
                ),
                hasRating:
                    button.dataset.hasRating === '1',
            });
        });
    });

    document.querySelectorAll(
        '.edit-content-item'
    ).forEach(button => {
        button.addEventListener('click', () => {
            prepareItemForm({
                sectionKey:
                    JSON.parse(
                        button.dataset.item
                    ).section_key,
                sectionLabel:
                    button.dataset.sectionLabel,
                labels: JSON.parse(
                    button.dataset.labels || '{}'
                ),
                hasRating:
                    button.dataset.hasRating === '1',
                item: JSON.parse(
                    button.dataset.item
                ),
            });
        });
    });

    itemForm?.addEventListener('submit', event => {
        event.preventDefault();

        if (!itemForm.reportValidity()) {
            return;
        }

        const button = event.submitter;
        const originalHtml = button.innerHTML;

        button.disabled = true;
        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

        request(new FormData(itemForm))
            .then(result => {
                itemModal.hide();
                toast('success', result.message);

                window.setTimeout(
                    () => window.location.reload(),
                    450
                );
            })
            .catch(error => {
                toast('error', error.message);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
    });

    document.querySelectorAll(
        '.delete-content-item'
    ).forEach(button => {
        button.addEventListener('click', () => {
            const execute = async () => {
                const data = new FormData();
                data.set('action', 'delete_item');
                data.set('id', button.dataset.id);

                try {
                    const result = await request(data);
                    toast('success', result.message);

                    window.setTimeout(
                        () => window.location.reload(),
                        400
                    );
                } catch (error) {
                    toast('error', error.message);
                }
            };

            if (
                window.RamkiAdmin
                && typeof RamkiAdmin.confirm === 'function'
            ) {
                RamkiAdmin.confirm(
                    'Delete this website section item?',
                    execute,
                    'Delete website item'
                );
            } else if (
                window.confirm(
                    'Delete this website section item?'
                )
            ) {
                execute();
            }
        });
    });
})();
