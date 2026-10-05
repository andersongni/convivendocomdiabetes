
(function($) {
    const sleep = async ( time ) => {
        return new Promise( ( resolve ) => setTimeout( resolve, time ) );
    };

    async function saveCustomizerSettings() {
        let promiseResolve;
        const promise = new Promise((resolve, reject) => {
            promiseResolve = resolve;
        })
        let doneCallback = () => {
            promiseResolve();
        }
        try {
            if (!_.isEmpty(wp.customize.dirtyValues())) {
                let executeCallback = true;
                wp.customize.bind('save', () => {
                    if (executeCallback) {
                        $(window).off('beforeunload');
                        setTimeout(doneCallback, 2000);
                        executeCallback = false;
                    }
                });
                wp.customize.previewer.save();
            } else {
                $(window).off('beforeunload');
                setTimeout(doneCallback, 500);
            }
        } catch (e) {
            doneCallback();
            console.error(e);
        }
        await promise;

    }
    function showOverlay(message) {
        var $overlay = jQuery('.mesmerize-customizer-overlay');

        if (!$overlay.length) {
            $overlay = jQuery('' + '<div class="mesmerize-customizer-overlay">\n' + '        <div class="mesmerize-customizer-overlay-content">\n' + '            <span class="mesmerize-customizer-overlay-loader"></span>\n' + '            <span class="mesmerize-customizer-overlay-message"></span>\n' + '        </div>\n' + '    </div>');

            jQuery('body').append($overlay);
        }

        $('.mesmerize-customizer-overlay-message').html(message);
        $overlay.fadeIn();
    }

    function hideOverlay() {
        var $overlay = jQuery('.mesmerize-customizer-overlay');
        $overlay.fadeOut();
    }

    function pluginNotice(message) {
        showOverlay(message);
    }
    
    let globalDataObject = window.mesmerizeCompanionInstallerData;
    const PLUGIN_STATUSES = {
        NOT_INSTALLED: 'not-installed',
        INSTALLED: 'installed',
        ACTIVE: 'active',
    };
    /**
     * Siteleads start
     */
    async function prepareSiteLeadsPlugin() {



        //same for the other file
        function getSiteLeadsBackendData(path, defaultValue) {
            return _.get(
                globalDataObject.siteLeads,
                path,
                defaultValue
            );
        }

        const siteLeadsIntegrationIsEnabled= getSiteLeadsBackendData('siteLeadsIntegrationIsEnabled');
        if(!siteLeadsIntegrationIsEnabled) {
            return;
        }

        function getTranslatedText(name) {
            return getSiteLeadsBackendData(['translations', name], name);
        }

        let requestIsPending = false;
        let currentStatus = getSiteLeadsBackendData('pluginStatus')
        async function onHandleButtonClick() {
            if (currentStatus === PLUGIN_STATUSES.ACTIVE) {
                return;
            }
            if (requestIsPending) {
                return;
            }


            requestIsPending = true;

            switch (currentStatus) {
                case PLUGIN_STATUSES.NOT_INSTALLED:
                    await installAndActivateSiteLeads();
                    break;
                case PLUGIN_STATUSES.INSTALLED:
                    await onActivateSiteLeads();
                    break;
            }
            requestIsPending = false;

        };

        async function installAndActivateSiteLeads() {
            try {
                const installResponse = await onInstallSiteLeadsPlugin();
                if (!installResponse) {
                    return false;
                }
                const activateResponse = await onActivateSiteLeads();
                if (!activateResponse) {
                    return false;
                }
                return true;
            } catch (e) {
                console.error(e);
                return false;

            }
        };
        async function onInstallSiteLeadsPlugin() {
            const slug = getSiteLeadsBackendData('pluginSlug');

            const promise = new Promise((resolve, reject) => {
                wp.updates.ajax( "install-plugin", {
                    slug: slug,
                    success: () => {
                        resolve();
                    },
                    error: e => {
                        if ('folder_exists' === e.errorCode) {
                            resolve();
                        } else {
                            reject();
                        }
                    }
                });

            });

            try {
                pluginNotice(getTranslatedText('installingSiteLeads'));
                await promise
                await sleep(100);
                return true;
            } catch (e) {
                console.error(e);
                return false;
            }
        };
        async function onActivateSiteLeads()  {
            const activationUrl = getSiteLeadsBackendData('activationLink')
            let promise = new Promise(async (resolve, reject) => {
                try {
                    let result = await fetch(  activationUrl)
                    if(!result.ok) {
                        reject(result.statusText)
                    }
                    resolve();
                } catch (e) {
                    reject(e)
                }


            });

            try {
                pluginNotice(getTranslatedText('activatingSiteLeads'));
                await promise;
                await sleep(100);
                await saveCustomizerSettings();
                await initSetupForSiteLeadsPlugin();
                await sleep(100);

                return true;
            } catch (e) {
                console.error(e);
                return false;
            }
        };
        async function initSetupForSiteLeadsPlugin() {
            const ajaxHandle = getSiteLeadsBackendData(
                'siteLeadsInitWpAjaxHandle'
            );
            const startSource =  getSiteLeadsBackendData('startSource');
            const nonce = getSiteLeadsBackendData('siteLeadsNonce');

            const promise = new Promise((resolve, reject) => {
                wp.ajax
                    .post(ajaxHandle, {
                        _wpnonce: nonce,
                        'start_source':startSource
                    })
                    .done((response) => {
                        resolve(response);
                    })
                    .fail((error) => {
                        reject(error);
                    });
            });
            try {
                pluginNotice(getTranslatedText('initSetupSiteLeads'));
                await promise;
                await sleep(100);
                //wp.customize.previewer.refresh();

            } catch (e) {
                console.error(e);
                return false;
            }
        };

        await onHandleButtonClick();
    }
    /**
     * Siteleads end
     */

    function getMesmerizeBackendData(path, defaultValue) {
        return _.get(
            globalDataObject,
            path,
            defaultValue
        );
    }


    function getMesmerizeCompanionTranslatedText(name) {
        return getMesmerizeBackendData(['translations', name], name);
    }

    //same for the other file

    async function onInstallMesmerizeCompanionPlugin() {
        const slug = getMesmerizeBackendData('mesmerizeCompanionSlug');
        const promise = new Promise((resolve, reject) => {
            wp.updates.ajax( "install-plugin", {
                slug: slug,
                success: () => {
                    resolve();
                },
                error: e => {
                    if ('folder_exists' === e.errorCode) {
                        resolve();
                    } else {
                        reject();
                    }
                }
            });

        });

        try {
            pluginNotice(getMesmerizeCompanionTranslatedText('installingCompanion'));
            await promise
            await sleep(100);
            return true;
        } catch (e) {
            console.error(e);
            return false;
        }
    }



    function forceCustomizerPublishedState() {
        wp?.customize?.state?.('saved')?.set?.(true);
        wp?.customize?.state?.('changesetStatus')?.set?.('publish');
    }
    async function onActivateMesmerizeCompanion() {
        const activationUrl = getMesmerizeBackendData('mesmerizeCompanionActivationLink')
        const customizerUrl = getMesmerizeBackendData('customizerUrl')
        if(!activationUrl) {
            return null;
        }
        let promise = new Promise(async (resolve, reject) => {
            try {
                let result = await fetch(  activationUrl)
                if(!result.ok) {
                    reject(result.statusText)
                }
                resolve();
            } catch (e) {
                reject(e)
            }


        });

        try {
            pluginNotice(getMesmerizeCompanionTranslatedText('activatingCompanion'));
            await promise;
            await sleep(100);

            const onRedirectAfterActivate = () => {
                if(customizerUrl) {
                    window.location = customizerUrl;
                } else {
                    window.location.reload();
                }
            }

            const isWpAdminPage = !wp.customize?.previewer
            //wp admin logic
            if(isWpAdminPage) {
                onRedirectAfterActivate()


                //customizer logic
            } else {
                forceCustomizerPublishedState();
                onRedirectAfterActivate()

            }

            return true;
        } catch (e) {
            console.error(e);
            return false;
        }
    }

    async function onInstallAndActivateMesmerizeCompanion () {
        try {
            const installResponse = await onInstallMesmerizeCompanionPlugin();
            if (!installResponse) {
                return false;
            }

            const activateResponse = await onActivateMesmerizeCompanion();
            if (!activateResponse) {
                return false;
            }
            return true;
        } catch (e) {
            console.error(e);
            return false;

        }
    };

async function installAndActivateMesmerizeCompanionIfNeeded () {
    const currentStatus = getMesmerizeBackendData('mesmerizeCompanionPluginStatus');
    switch (currentStatus) {
        case PLUGIN_STATUSES.NOT_INSTALLED:
            await onInstallAndActivateMesmerizeCompanion();
            break;
        case PLUGIN_STATUSES.INSTALLED:
            await onActivateMesmerizeCompanion();
            break;
    }
}
    let isPending = false;
    async function onInstallOrActivate(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        if(isPending) {
            return
        }
        isPending = true;
        pluginNotice(getMesmerizeCompanionTranslatedText('preparing'));
        try {
            await saveCustomizerSettings();
        } catch(e) {
            console.error(e);
        }

        try {
            await prepareSiteLeadsPlugin()
        } catch(e) {
            console.error(e);
        }
         try{
            await installAndActivateMesmerizeCompanionIfNeeded();
         } catch(e) {
            console.error(e)
         }
        isPending = false;

        // in case this gets stuck remove overlay after 10 seconds. If everything goes ok the overlay should stay until the page gets redirected
        setTimeout(() => {
            hideOverlay();
        }, 10000)

    }




    const onAddInstallListener = () => {
        let buttons = [...document.querySelectorAll('#extendthemes_start_with_homepage .install-frontpage, .customize-control-ope-info a[install-source]')];
        buttons.forEach(button => {
            button.removeEventListener('click', onInstallOrActivate)
            button.addEventListener('click', onInstallOrActivate)
        })

    }

    $(document).ready(function() {
        onAddInstallListener();

        if(wp && wp.customize && wp.customize.bind) {
            wp.customize.bind( 'pane-contents-reflowed', onAddInstallListener );
            wp.customize.bind( 'save', onAddInstallListener );
        }

    });

})(jQuery)
