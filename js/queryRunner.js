/* ── QueryRunner ── */
(() => {
    const modal      = new bootstrap.Modal(document.getElementById('qr-modal'));
    const modalBody  = document.getElementById('qr-modal-body');
    const btnConfirm = document.getElementById('qr-btn-confirmer');
    const spinner    = document.getElementById('qr-spinner');
    const zoneListe  = document.getElementById('qr-zone-liste');
    const zoneRes    = document.getElementById('qr-zone-resultat');

    let currentIndex  = null;
    let currentParams = [];

    // ── Clic sur "Lancer" ──
    document.querySelectorAll('.qr-btn-lancer').forEach(btn => {
        btn.addEventListener('click', () => {
            currentIndex  = btn.dataset.index;
            currentParams = JSON.parse(btn.dataset.params);

            if (currentParams.length === 0) {
                executer({});
                return;
            }

            document.getElementById('qr-modal-label').textContent =
                'Paramètres — ' + btn.dataset.sujet;

            modalBody.innerHTML = currentParams.map(nom => `
                <div class="mb-3">
                    <label class="form-label fw-semibold">${nom}</label>
                    <input type="text"
                           class="form-control qr-param-input"
                           data-nom="${nom}"
                           placeholder="${nom}">
                </div>
            `).join('');

            document.getElementById('qr-modal').addEventListener('shown.bs.modal', () => {
                modalBody.querySelector('.qr-param-input')?.focus();
            }, { once: true });

            modalBody.addEventListener('keydown', e => {
                if (e.key === 'Enter') btnConfirm.click();
            }, { once: true });

            modal.show();
        });
    });

    // ── Confirmer la modale ──
    btnConfirm.addEventListener('click', () => {
        const valeurs = {};
        modalBody.querySelectorAll('.qr-param-input').forEach(input => {
            valeurs[input.dataset.nom] = input.value;
        });
        modal.hide();
        executer(valeurs);
    });

    // ── Appel AJAX ──
    function executer(valeurs) {
        zoneListe.style.display = 'none';
        spinner.style.display   = 'block';
        zoneRes.style.display   = 'none';

        const body = new FormData();
        body.append('action', 'qr_executer');
        body.append('index',  currentIndex);
        for (const [nom, val] of Object.entries(valeurs)) {
            body.append('param_' + nom, val);
        }

        fetch(window.location.href, { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                spinner.style.display = 'none';
                data.erreur ? afficherErreur(data.erreur) : afficherResultat(data);
            })
            .catch(err => {
                spinner.style.display = 'none';
                afficherErreur(err.message);
            });
    }

    // ── Affichage résultat ──
    function afficherResultat(data) {
        // En-tête du résultat
        document.getElementById('qr-res-sujet').textContent       = data.sujet;
        document.getElementById('qr-res-description').textContent = data.description;
        document.getElementById('qr-res-count').textContent       = data.rows.length + ' ligne(s)';

        const thead = document.getElementById('qr-res-thead');
        const tbody = document.getElementById('qr-res-tbody');
        thead.innerHTML = '';
        tbody.innerHTML = '';

        if (data.rows.length === 0) {
            tbody.innerHTML = '<tr><td class="text-muted p-3">Aucun résultat.</td></tr>';
        } else {
            Object.keys(data.rows[0]).forEach(col => {
                thead.insertAdjacentHTML('beforeend', `<th>${col}</th>`);
            });
            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                Object.values(row).forEach(val => {
                    const td = document.createElement('td');
                    td.textContent = val ?? 'NULL';
                    if (val === null) td.classList.add('text-muted', 'fst-italic');
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }

        zoneRes.style.display = 'block';
    }

    function afficherErreur(msg) {
        document.getElementById('qr-res-sujet').textContent       = 'Erreur';
        document.getElementById('qr-res-description').textContent = '';
        document.getElementById('qr-res-count').textContent       = '';
        document.getElementById('qr-res-thead').innerHTML         = '';
        document.getElementById('qr-res-tbody').innerHTML         =
            `<tr><td class="text-danger p-3"><strong>Erreur :</strong> ${msg}</td></tr>`;
        zoneRes.style.display = 'block';
    }

    // ── Retour à la liste ──
    document.getElementById('qr-btn-retour').addEventListener('click', () => {
        zoneRes.style.display  = 'none';
        zoneListe.style.display = 'block';
    });

})();
