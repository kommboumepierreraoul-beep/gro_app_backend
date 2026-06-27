<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Upload</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .container { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        input, button { margin: 10px 0; padding: 8px; }
        button { background: blue; color: white; border: none; cursor: pointer; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
        #result { margin-top: 20px; }
        img { max-width: 100%; max-height: 300px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Test d'upload de fichiers</h2>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div>
                <label>Sélectionnez un fichier :</label>
                <input type="file" name="file" id="file" accept="image/*">
            </div>
            <button type="submit">Uploader</button>
        </form>
        
        <div id="result"></div>
    </div>

    <script>
        // Attendre que le DOM soit chargé
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            const resultDiv = document.getElementById('result');
            
            form.onsubmit = async function(e) {
                e.preventDefault();
                
                const fileInput = document.getElementById('file');
                const file = fileInput.files[0];
                
                if (!file) {
                    resultDiv.innerHTML = '<p class="error">Veuillez sélectionner un fichier</p>';
                    return;
                }
                
                const formData = new FormData();
                formData.append('file', file);
                
                resultDiv.innerHTML = '<p>Upload en cours...</p>';
                
                try {
                    const response = await fetch('/test-upload', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="success">
                                <h3>Upload reussi !</h3>
                                <p><strong>Chemin:</strong> ${data.path}</p>
                                <p><strong>URL:</strong> <a href="${data.url}" target="_blank">${data.url}</a></p>
                                <h4>Apercu:</h4>
                                <img src="${data.url}" alt="Uploaded image">
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = '<p class="error">Echec de l\'upload</p>';
                    }
                } catch (error) {
                    resultDiv.innerHTML = '<p class="error">Erreur: ' + error.message + '</p>';
                    console.error('Erreur:', error);
                }
            };
        });
    </script>
</body>
</html>