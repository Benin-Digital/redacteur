<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="550" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">
                    
                    <!-- En-tête -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px 40px; text-align: center;">
                            <img src="https://benin-digital.com/wp-content/uploads/2021/08/cropped-Benin-digital-182.png" 
                                 alt="BENIN DIGITAL" 
                                 style="max-width: 60px; height: auto; margin-bottom: 8px;">
                            <h1 style="color: #2a7a4a; font-size: 20px; margin: 0;">Réinitialisation du mot de passe</h1>
                        </td>
                    </tr>
                    
                    <!-- Corps -->
                    <tr>
                        <td style="padding: 35px;">
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                                Bonjour <strong>{{ $user->firstname }} {{ $user->name }}</strong>,
                            </p>
                            
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 25px;">
                                Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.
                            </p>
                            
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 15px;">
                                Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :
                            </p>
                            
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $resetUrl }}" 
                                           style="display: inline-block; background-color: #3cb77c; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; margin: 15px 0;">
                                            Réinitialiser mon mot de passe
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Expiration -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef7e0; border-radius: 8px; padding: 15px; margin: 20px 0;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; color: #856404; font-size: 13px;">
                                            Ce lien expirera dans <strong>60 minutes</strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #8a9a8a; font-size: 13px; margin-top: 25px;">
                                Si vous n'êtes pas à l'origine de cette demande, ignorez cet email. Votre mot de passe restera inchangé.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Pied de page -->
                    <tr>
                        <td style="background-color: #f5f7f5; padding: 20px 30px; text-align: center;">
                            <p style="color: #6a7a6a; font-size: 11px; margin: 0;">
                                © {{ date('Y') }} BENIN DIGITAL - Sécurité des comptes
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>