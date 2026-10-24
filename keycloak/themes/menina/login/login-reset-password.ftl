<#import "template.ftl" as layout>
<#-- Basado en login-reset-password.ftl del tema base: mismo campo (username) y acción, con el orden
     de nuestra pantalla de login: explicación arriba, botón ancho y "volver" abajo -->
<@layout.registrationLayout displayInfo=false displayMessage=!messagesPerField.existsError('username'); section>
    <#if section = "header">
        ${msg("emailForgotTitle")}
    <#elseif section = "subtitulo">
        <p class="mc-subtitulo">
            <#if realm.duplicateEmailsAllowed>${msg("emailInstructionUsername")}<#else>${msg("emailInstruction")}</#if>
        </p>
    <#elseif section = "form">
        <form id="kc-reset-password-form" class="mc-formulario" action="${url.loginAction}" method="post" novalidate>
            <div class="${properties.kcFormGroupClass!}">
                <label for="username" class="${properties.kcLabelClass!}"><#if !realm.loginWithEmailAllowed>${msg("username")}<#elseif !realm.registrationEmailAsUsername>${msg("usernameOrEmail")}<#else>${msg("email")}</#if></label>
                <input type="email" id="username" name="username" class="${properties.kcInputClass!}" autofocus autocomplete="username"
                       value="${(auth.attemptedUsername!'')}" placeholder="${msg('mcCorreoEjemplo')}"
                       aria-invalid="<#if messagesPerField.existsError('username')>true</#if>"
                       <#if messagesPerField.existsError('username')>aria-describedby="input-error-username"</#if>
                       dir="ltr"/>
                <#if messagesPerField.existsError('username')>
                    <span id="input-error-username" class="${properties.kcInputErrorMessageClass!}" aria-live="polite">
                        ${kcSanitize(messagesPerField.get('username'))?no_esc}
                    </span>
                </#if>
            </div>

            <div id="kc-form-buttons" class="mc-botones">
                <input class="${properties.kcButtonClass!} ${properties.kcButtonPrimaryClass!} ${properties.kcButtonBlockClass!}" type="submit" value="${msg("doSubmit")}"/>
            </div>

            <p class="mc-pie-formulario">
                <a class="mc-enlace" href="${url.loginUrl}">${kcSanitize(msg("backToLogin"))?no_esc}</a>
            </p>
        </form>
    </#if>
</@layout.registrationLayout>
