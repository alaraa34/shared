        const editor = document.getElementById('editor');
        const toolbarButtons = document.querySelectorAll('[data-command]');
        const formatBlockSelect = document.getElementById('formatBlock');
        const textColorInput = document.getElementById('textColor');
        const createLinkBtn = document.getElementById('createLink');
        const clearEditorBtn = document.getElementById('clearEditor');
        
        // Fonction pour exécuter les commandes
        function executeCommand(command, value = null) {
            document.execCommand(command, false, value);
            editor.focus();
            updateToolbarState();
        }
        
        // Gestion des boutons de la barre d'outils
        toolbarButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const command = button.getAttribute('data-command');
                executeCommand(command);
            });
        });
        
        // Gestion du formatage de bloc (titres)
        formatBlockSelect.addEventListener('change', (e) => {
            executeCommand('formatBlock', e.target.value);
        });
        
        // Gestion de la couleur du texte
        //textColorInput.addEventListener('change', (e) => {
        //    executeCommand('foreColor', e.target.value);
        //});
        
        // Gestion de l'insertion de lien
        createLinkBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const url = prompt('Entrez l\'URL du lien:', 'https://');
            if (url && url !== 'https://') {
                executeCommand('createLink', url);
            }
        });
        
        // Effacer tout le contenu
        clearEditorBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Voulez-vous vraiment effacer tout le contenu ?')) {
                editor.innerHTML = '<p><br></p>';
                editor.focus();
            }
        });
        
        // Mettre à jour l'état des boutons (actif/inactif)
        function updateToolbarState() {
            toolbarButtons.forEach(button => {
                const command = button.getAttribute('data-command');
                if (document.queryCommandState(command)) {
                    button.classList.add('active');
                } else {
                    button.classList.remove('active');
                }
            });
        }
        
        // Mettre à jour l'état lors de la sélection
        editor.addEventListener('mouseup', updateToolbarState);
        editor.addEventListener('keyup', updateToolbarState);
        
        // Soumission du formulaire
        document.getElementById('editorForm').addEventListener('submit', function(e) {
            const contenu = editor.innerHTML;
            
            // Vérifier si le contenu n'est pas vide
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contenu;
            const textContent = tempDiv.textContent.trim();
            
            //if (textContent === '' || textContent === 'Commencez à écrire ici...') {
            //    e.preventDefault();
            //    alert('⚠️ Veuillez saisir du contenu avant d\'enregistrer.');
            //    return false;
            //}
            
            document.getElementById('contenu').value = contenu;
        });
        
        // Fonction pour réinitialiser le formulaire
        function resetForm() {
            document.getElementById('editorForm').reset();
            editor.innerHTML = '<p>Commencez à écrire ici...</p>';
            editor.focus();
        }
        
        // Supprimer le texte par défaut au premier focus
        editor.addEventListener('focus', function() {
            if (this.textContent.trim() === 'Commencez à écrire ici...') {
                this.innerHTML = '<p><br></p>';
            }
        }, { once: true });