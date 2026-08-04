const loadingForms = new Set();

const setFormLoading = (form) => {
    if (loadingForms.has(form)) {
        return;
    }

    loadingForms.add(form);
    form.setAttribute('aria-busy', 'true');
    form.classList.add('sf-is-loading');

    const loadingText =
        form.dataset.sfLoadingText
        || 'Loading...';

    form.querySelectorAll(
        'button[type="submit"], input[type="submit"]'
    ).forEach((control) => {
        control.dataset.sfWasDisabled =
            control.disabled ? '1' : '0';

        control.disabled = true;

        if (control instanceof HTMLButtonElement) {
            control.dataset.sfOriginalHtml =
                control.innerHTML;

            control.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>'
                + loadingText;
        } else if (
            control instanceof HTMLInputElement
        ) {
            control.dataset.sfOriginalValue =
                control.value;

            control.value = loadingText;
        }
    });
};

const resetFormLoading = (form) => {
    loadingForms.delete(form);
    form.removeAttribute('aria-busy');
    form.classList.remove('sf-is-loading');

    form.querySelectorAll(
        'button[type="submit"], input[type="submit"]'
    ).forEach((control) => {
        control.disabled =
            control.dataset.sfWasDisabled === '1';

        if (
            control instanceof HTMLButtonElement
            && control.dataset.sfOriginalHtml
                !== undefined
        ) {
            control.innerHTML =
                control.dataset.sfOriginalHtml;
        }

        if (
            control instanceof HTMLInputElement
            && control.dataset.sfOriginalValue
                !== undefined
        ) {
            control.value =
                control.dataset.sfOriginalValue;
        }

        delete control.dataset.sfWasDisabled;
        delete control.dataset.sfOriginalHtml;
        delete control.dataset.sfOriginalValue;
    });
};

const enableLoadingForms = () => {
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (
                ! (form instanceof HTMLFormElement)
                || ! form.matches('[data-sf-loading]')
            ) {
                return;
            }

            if (! form.checkValidity()) {
                return;
            }

            setFormLoading(form);
        }
    );

    window.addEventListener(
        'pageshow',
        () => {
            document
                .querySelectorAll(
                    'form[data-sf-loading]'
                )
                .forEach(resetFormLoading);
        }
    );
};

const hasInteractiveContent = (card) =>
    card.querySelector(
        'a, button, input, select, textarea, form, details'
    ) !== null;

const enableDrilldownCards = () => {
    document
        .querySelectorAll(
            '[data-sf-drilldown-scope]'
        )
        .forEach((scope) => {
            const url =
                scope.dataset.sfDrilldownUrl;

            if (! url) {
                return;
            }

            scope
                .querySelectorAll(
                    '.row.g-3.mb-4 > [class*="col-"] > .app-card'
                )
                .forEach((card) => {
                    if (hasInteractiveContent(card)) {
                        return;
                    }

                    card.classList.add(
                        'sf-drilldown-card'
                    );

                    card.setAttribute(
                        'role',
                        'link'
                    );

                    card.setAttribute(
                        'tabindex',
                        '0'
                    );

                    card.setAttribute(
                        'aria-label',
                        `${card.textContent.trim()} — open details`
                    );

                    card.addEventListener(
                        'click',
                        () => {
                            window.location.assign(url);
                        }
                    );

                    card.addEventListener(
                        'keydown',
                        (event) => {
                            if (
                                event.key !== 'Enter'
                                && event.key !== ' '
                            ) {
                                return;
                            }

                            event.preventDefault();
                            window.location.assign(url);
                        }
                    );
                });
        });
};

document.addEventListener(
    'DOMContentLoaded',
    () => {
        enableLoadingForms();
        enableDrilldownCards();
    }
);
