<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de changement de mot de passe</title>
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
                            <h1 style="color: #2a7a4a; font-size: 20px; margin: 0;">Confirmation de modification</h1>
                        </td>
                    </tr>
                    
                    <!-- Corps -->
                    <tr>
                        <td style="padding: 35px;">
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                                Bonjour <strong>{{ $user->firstname }} {{ $user->name }}</strong>,
                            </p>
                            
                            @if($type === 'reset')
                                <div style="background-color: #e8f5ee; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px;">
                                    <p style="color: #2a7a4a; font-size: 16px; margin: 10px 0 0 0; font-weight: 600;">
                                        Votre mot de passe a été réinitialisé avec succès
                                    </p>
                                </div>
                            @else
                                <div style="background-color: #e8f5ee; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px;">
                                    <p style="color: #2a7a4a; font-size: 16px; margin: 10px 0 0 0; font-weight: 600;">
                                        Votre mot de passe a été modifié avec succès
                                    </p>
                                </div>
                            @endif
                            
                            <!-- Alerte sécurité -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff3e0; border-radius: 8px; padding: 15px; margin: 20px 0;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; color: #856404; font-size: 13px;">
                                            <strong>Si vous n'êtes pas à l'origine de cette modification</strong>, veuillez contacter immédiatement l'administrateur.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #6a7a6a; font-size: 13px; margin-top: 20px; text-align: center;">
                                Cette notification a été envoyée automatiquement pour votre sécurité.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Pied de page -->
                    <tr>
                        <td style="background-color: #f5f7f5; padding: 20px 30px; text-align: center;">
                            <p style="color: #6a7a6a; font-size: 11px; margin: 0;">
                                © {{ date('Y') }} BENIN DIGITAL - Sécurité des comptes
                            </p>
                            <p style="color: #8a9a8a; font-size: 10px; margin: 5px 0 0 0;">
                                lot 85, rue 12.200. Haie Vive, Cotonou. Bénin
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>