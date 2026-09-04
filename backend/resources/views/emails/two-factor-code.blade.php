{{-- resources/views/emails/two-factor-code.blade.php --}}

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de verification - Agent IA Redacteur Web</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <!-- Conteneur principal -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">
                    
                    <!-- En-tete -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px 40px; text-align: center;">
                            <h1 style="color: #2a7a4a; font-size: 28px; margin: 0; font-weight: 700;">Agent IA</h1>
                            <p style="color: #6a7a6a; font-size: 16px; margin: 5px 0 0;">Redacteur Web</p>
                            <p style="color: #8a9a8a; font-size: 13px; margin: 2px 0 0;">Automatisation SEO</p>
                        </td>
                    </tr>
                    
                    <!-- Corps -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #2a7a4a; font-size: 22px; margin-top: 0; margin-bottom: 20px;">Code de verification</h2>
                            
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                                Bonjour <strong>{{ $user->firstname }} {{ $user->name }}</strong>,
                            </p>
                            
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                                Vous avez demande a vous connecter a votre compte administrateur sur <strong>Agent IA Redacteur Web</strong>. Veuillez utiliser le code ci-dessous pour valider votre connexion :
                            </p>
                            
                            <!-- Encadre du code -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f8f2; border-radius: 12px; padding: 20px; margin: 25px 0;">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0; font-size: 14px; color: #2a7a4a; font-weight: 500; letter-spacing: 1px;">VOTRE CODE DE VERIFICATION</p>
                                        <p style="margin: 10px 0 0 0; font-size: 42px; font-weight: 700; color: #1a2a1a; font-family: 'Courier New', monospace; letter-spacing: 12px; background: #ffffff; padding: 15px 25px; border-radius: 8px; display: inline-block;">
                                            {{ $code }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #3a4a3a; font-size: 15px; line-height: 1.5; margin-bottom: 10px;">
                                Ce code est valable pendant <strong>{{ $expires_in }} minutes</strong>.
                            </p>
                            
                            <!-- Lien direct vers la page de verification -->
                            <p style="color: #3a4a3a; font-size: 15px; line-height: 1.5; margin-bottom: 10px;">
                                Cliquez sur le lien ci-dessous pour acceder directement a la page de verification :
                            </p>
                            
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://scribe.benin-digital.app/verification" 
                                            style="display: inline-block; background-color: #f0f8f2; color: #2a7a4a; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; border: 2px solid #2a7a4a; margin: 10px 0;">
                                            Aller a la page de verification
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #6a7a6a; background-color: #f8faf8; padding: 12px 16px; border-radius: 8px; font-size: 14px; border-left: 4px solid #2a7a4a; margin: 20px 0;">
                                Si vous n'etes pas a l'origine de cette demande, ignorez simplement cet email. Votre compte reste securise.
                            </p>
                            
                            <!-- Lien vers la plateforme -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://scribe.benin-digital.app/login" 
                                            style="display: inline-block; background-color: #2a7a4a; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 10px 0;">
                                            Retourner a la connexion
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Pied de page -->
                    <tr>
                        <td style="background-color: #f5f7f5; padding: 25px 40px; text-align: center; border-top: 1px solid #e0e6e0;">
                            <p style="color: #6a7a6a; font-size: 12px; margin: 0 0 10px 0;">
                                &copy; {{ date('Y') }} BENIN DIGITAL - Tous droits reserves
                            </p>
                            <p style="color: #8a9a8a; font-size: 11px; margin: 0;">
                                Cet email a ete envoye automatiquement, merci de ne pas y repondre.
                            </p>
                            <p style="color: #8a9a8a; font-size: 11px; margin: 5px 0 0 0;">
                                lot 85, rue 12.200. Haie Vive, Cotonou. Benin
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>