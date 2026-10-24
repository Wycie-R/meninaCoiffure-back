<#import "template.ftl" as layout>
<#-- Basado en el login.ftl del tema base de Keycloak 26: mismos campos y nombres (username, password,
     rememberMe, credentialId), con la estructura y los textos de nuestra pantalla de login -->
<@layout.registrationLayout displayMessage=!messagesPerField.existsError('username','password') displayInfo=realm.password && realm.registrationAllowed && !registrationDisabled??; section>
    <#if section = "header">
        ${msg("loginAccountTitle")}
    <#elseif section = "subtitulo">
        <p class="mc-subtitulo">${msg("mcLoginSubtitulo")}</p>
    <#elseif section = "form">

        <div id="kc-form">
            <#if realm.password>
                <form id="kc-form-login" class="mc-formulario" onsubmit="login.disabled = true; return true;" action="${url.loginAction}" method="post" novalidate>
                    <#if !usernameHidden??>
                        <div class="${properties.kcFormGroupClass!}">
                            <label for="username" class="${properties.kcLabelClass!}"><#if !realm.loginWithEmailAllowed>${msg("username")}<#elseif !realm.registrationEmailAsUsername>${msg("usernameOrEmail")}<#else>${msg("email")}</#if></label>

                            <input tabindex="2" id="username" class="${properties.kcInputClass!}" name="username" value="${(login.username!'')}" type="email" autofocus autocomplete="username"
                                   placeholder="${msg('mcCorreoEjemplo')}"
                                   aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>"
                                   <#if messagesPerField.existsError('username','password')>aria-describedby="input-error"</#if>
                                   dir="ltr"
                            />

                            <#if messagesPerField.existsError('username','password')>
                                <span id="input-error" class="${properties.kcInputErrorMessageClass!}" aria-live="polite">
                                    ${kcSanitize(messagesPerField.getFirstError('username','password'))?no_esc}
                                </span>
                            </#if>
                        </div>
                    </#if>

                    <div class="${properties.kcFormGroupClass!}">
                        <label for="password" class="${properties.kcLabelClass!}">${msg("password")}</label>

                        <div class="${properties.kcInputGroup!}" dir="ltr">
                            <input tabindex="3" id="password" class="${properties.kcInputClass!}" name="password" type="password" autocomplete="current-password"
                                   aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>"
                            />
                            <button class="${properties.kcFormPasswordVisibilityButtonClass!}" type="button" aria-label="${msg("showPassword")}"
                                    aria-controls="password" data-password-toggle tabindex="4"
                                    data-icon-show="${properties.kcFormPasswordVisibilityIconShow!}" data-icon-hide="${properties.kcFormPasswordVisibilityIconHide!}"
                                    data-label-show="${msg('showPassword')}" data-label-hide="${msg('hidePassword')}">
                                <i class="${properties.kcFormPasswordVisibilityIconShow!}" aria-hidden="true"></i>
                            </button>
                        </div>

                        <#if usernameHidden?? && messagesPerField.existsError('username','password')>
                            <span id="input-error" class="${properties.kcInputErrorMessageClass!}" aria-live="polite">
                                ${kcSanitize(messagesPerField.getFirstError('username','password'))?no_esc}
                            </span>
                        </#if>
                    </div>

                    <div class="mc-opciones">
                        <#if realm.rememberMe && !usernameHidden??>
                            <label class="mc-casilla" for="rememberMe">
                                <input tabindex="5" id="rememberMe" name="rememberMe" type="checkbox" class="mc-casilla-entrada" <#if login.rememberMe??>checked</#if>>
                                <span class="mc-casilla-marca" aria-hidden="true"></span>
                                <span class="mc-casilla-texto">${msg("rememberMe")}</span>
                            </label>
                        </#if>
                        <#if realm.resetPasswordAllowed>
                            <a tabindex="6" class="mc-enlace" href="${url.loginResetCredentialsUrl}">${msg("doForgotPassword")}</a>
                        </#if>
                    </div>

                    <div id="kc-form-buttons" class="mc-botones">
                        <input type="hidden" id="id-hidden-input" name="credentialId" <#if auth.selectedCredential?has_content>value="${auth.selectedCredential}"</#if>/>
                        <input tabindex="7" class="${properties.kcButtonClass!} ${properties.kcButtonPrimaryClass!} ${properties.kcButtonBlockClass!}" name="login" id="kc-login" type="submit" value="${msg("doLogIn")}"/>
                    </div>
                </form>
            </#if>
        </div>
        <script type="module" src="${url.resourcesPath}/js/passwordVisibility.js"></script>
    <#elseif section = "info" >
        <#if realm.password && realm.registrationAllowed && !registrationDisabled??>
            <div id="kc-registration" class="mc-alta-tarjeta">
                <span class="mc-alta-pregunta">${msg("noAccount")}</span>
                <a tabindex="8" class="${properties.kcButtonClass!} ${properties.kcButtonDefaultClass!} ${properties.kcButtonBlockClass!}" href="${url.registrationUrl}">${msg("doRegister")}</a>
            </div>
        </#if>
    <#elseif section = "socialProviders" >
        <#if realm.password && social?? && social.providers?has_content>
            <div id="kc-social-providers" class="mc-social">
                <p class="mc-social-titulo">${msg("identity-provider-login-label")}</p>
                <ul class="mc-social-lista">
                    <#list social.providers as p>
                        <li>
                            <a id="social-${p.alias}" class="${properties.kcButtonClass!} ${properties.kcButtonDefaultClass!} ${properties.kcButtonBlockClass!}" type="button" href="${p.loginUrl}">
                                ${p.displayName!}
                            </a>
                        </li>
                    </#list>
                </ul>
            </div>
        </#if>
    </#if>
</@layout.registrationLayout>
