<#import "footer.ftl" as loginFooter>
<#-- Monograma del salón: mismo SVG que src/resources/js/Components/Monograma.vue -->
<#macro monograma tamano clase="">
    <svg class="${clase}" width="${tamano}" height="${tamano}" viewBox="0 0 48 48" role="img" aria-label="Menina Coiffure">
        <#list [["-44", "#aeb9c7"], ["-22", "#8fd5b5"], ["0", "#d9a9e9"], ["22", "#f2bf70"], ["44", "#f08ca5"]] as mechon>
            <path d="M24 41 C 21.2 32, 27 23.5, 22.6 8.5 C 28.6 16.5, 29 30, 24 41 Z" fill="${mechon[1]}" transform="rotate(${mechon[0]} 24 41)" />
        </#list>
        <circle cx="24" cy="41" r="3.6" fill="#17111c" stroke="#f6eff4" stroke-width="1.4" />
    </svg>
</#macro>
<#-- Mismo layout que Login.vue: panel de marca a la izquierda (escritorio) y el formulario a la derecha -->
<#-- displayRequiredFields no se usa, pero se conserva porque las páginas de "base" (registro) lo envían -->

<#macro registrationLayout bodyClass="" displayInfo=false displayMessage=true displayRequiredFields=false>
<!DOCTYPE html>
<html class="${properties.kcHtmlClass!}" lang="${(locale.currentLanguageTag)!'es'}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="robots" content="noindex, nofollow">
    <#if properties.meta?has_content>
        <#list properties.meta?split(' ') as meta>
            <meta name="${meta?split('==')[0]}" content="${meta?split('==')[1]}"/>
        </#list>
    </#if>
    <title>${msg("loginTitle",(realm.displayName!''))}</title>
    <#-- La misma tipografía de títulos que la app (Gloock, desde Bunny Fonts) -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=gloock:400&display=swap" rel="stylesheet" />
    <#if properties.styles?has_content>
        <#list properties.styles?split(' ') as style>
            <link href="${url.resourcesPath}/${style}" rel="stylesheet" />
        </#list>
    </#if>
    <#if scripts??>
        <#list scripts as script>
            <script src="${script}" type="text/javascript"></script>
        </#list>
    </#if>
    <script type="importmap">
        {
            "imports": {
                "rfc4648": "${url.resourcesCommonPath}/vendor/rfc4648/rfc4648.js"
            }
        }
    </script>
    <#-- Si la sesión se inicia en otra pestaña, esta se actualiza sola -->
    <script type="module">
        import { startSessionPolling } from "${url.resourcesPath}/js/authChecker.js";

        startSessionPolling(
          "${url.ssoLoginInOtherTabsUrl?no_esc}"
        );
    </script>
</head>

<body class="${properties.kcBodyClass!} ${bodyClass}">
<div class="mc-pagina">
    <section class="mc-marca" aria-hidden="true">
        <div class="mc-marca-resplandor"></div>

        <div class="mc-marca-logo">
            <@monograma tamano="36" />
            <span class="mc-nombre">Menina Coiffure</span>
        </div>

        <div class="mc-marca-centro">
            <@monograma tamano="240" clase="mc-monograma-grande" />
            <p class="mc-lema">La agenda del salón, siempre a mano.</p>
            <p class="mc-bajada">Reservas, servicios y equipo de Menina Coiffure en un mismo lugar.</p>
        </div>

        <p class="mc-lugar">Villa Elisa, Paraguay</p>
    </section>

    <main class="mc-principal">
        <div class="mc-caja">
            <#-- En celular el panel de marca no se ve: el logo va arriba del formulario -->
            <div class="mc-logo-celular">
                <@monograma tamano="44" />
                <span class="mc-nombre mc-nombre-grande">Menina Coiffure</span>
            </div>

            <#if !(auth?has_content && auth.showUsername() && !auth.showResetCredentials())>
                <h1 id="kc-page-title" class="mc-titulo"><#nested "header"></h1>
            <#else>
                <#nested "show-username">
                <div id="kc-username" class="mc-usuario-intentado">
                    <span id="kc-attempted-username">${auth.attemptedUsername}</span>
                    <a id="reset-login" href="${url.loginRestartFlowUrl}" class="mc-enlace">
                        <i class="${properties.kcResetFlowIcon!}" aria-hidden="true"></i>
                        ${msg("restartLoginTooltip")}
                    </a>
                </div>
            </#if>

            <#-- Texto bajo el título: solo lo usan las páginas que definen la sección "subtitulo" -->
            <#nested "subtitulo">

            <#-- Las acciones iniciadas por la app no muestran el aviso de "tienes que completar esto" -->
            <#if displayMessage && message?has_content && (message.type != 'warning' || !isAppInitiatedAction??)>
                <div class="${properties.kcAlertClass!} mc-alerta-${message.type}" role="alert">
                    <#if message.type = 'success'><span class="${properties.kcFeedbackSuccessIcon!}" aria-hidden="true"></span></#if>
                    <#if message.type = 'warning'><span class="${properties.kcFeedbackWarningIcon!}" aria-hidden="true"></span></#if>
                    <#if message.type = 'error'><span class="${properties.kcFeedbackErrorIcon!}" aria-hidden="true"></span></#if>
                    <#if message.type = 'info'><span class="${properties.kcFeedbackInfoIcon!}" aria-hidden="true"></span></#if>
                    <span class="${properties.kcAlertTitleClass!}">${kcSanitize(message.summary)?no_esc}</span>
                </div>
            </#if>

            <div id="kc-content">
                <#nested "form">

                <#if auth?has_content && auth.showTryAnotherWayLink()>
                    <form id="kc-select-try-another-way-form" action="${url.loginAction}" method="post">
                        <div class="${properties.kcFormGroupClass!}">
                            <input type="hidden" name="tryAnotherWay" value="on"/>
                            <a href="#" id="try-another-way" class="mc-enlace"
                               onclick="document.forms['kc-select-try-another-way-form'].requestSubmit();return false;">${msg("doTryAnotherWay")}</a>
                        </div>
                    </form>
                </#if>

                <#nested "socialProviders">

                <#if displayInfo>
                    <div id="kc-info" class="${properties.kcSignUpClass!}">
                        <div id="kc-info-wrapper" class="${properties.kcInfoAreaWrapperClass!}">
                            <#nested "info">
                        </div>
                    </div>
                </#if>
            </div>

            <p class="mc-volver">
                <a href="${properties.urlSalon!'/'}" class="mc-volver-enlace">
                    <i class="mc-icono mc-icono-flecha" aria-hidden="true"></i>
                    Volver al salón
                </a>
            </p>

            <@loginFooter.content/>
        </div>
    </main>
</div>
</body>
</html>
</#macro>
