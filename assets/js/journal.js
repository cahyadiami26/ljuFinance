/* =====================================================================
   LJU Finance — editor baris jurnal (tanpa dependency)
   ===================================================================== */
(function () {
    'use strict';

    var tbody = document.getElementById('journal-lines');
    var form = document.getElementById('journal-form');
    if (!tbody || !form) {
        return;
    }

    var template = document.getElementById('journal-line-template');
    var counter = tbody.querySelectorAll('tr[data-line]').length;

    function angka(value) {
        var digits = String(value == null ? '' : value).replace(/[^\d]/g, '');
        return digits === '' ? 0 : parseInt(digits, 10);
    }

    function format(value) {
        var n = angka(value);
        return n === 0 ? '' : n.toLocaleString('id-ID');
    }

    function rows() {
        return Array.prototype.slice.call(tbody.querySelectorAll('tr[data-line]'));
    }

    function dimensiRowOf(row) {
        return row.nextElementSibling && row.nextElementSibling.hasAttribute('data-dimensi')
            ? row.nextElementSibling
            : null;
    }

    function reindex() {
        rows().forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
            });
            var no = row.querySelector('.jline-no');
            if (no) {
                no.textContent = String(index + 1);
            }
            var dimensi = dimensiRowOf(row);
            if (dimensi) {
                dimensi.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
                });
            }
        });
    }

    function recalc() {
        var debit = 0;
        var kredit = 0;

        rows().forEach(function (row) {
            var d = row.querySelector('input[name$="[debit]"]');
            var k = row.querySelector('input[name$="[kredit]"]');
            debit += d ? angka(d.value) : 0;
            kredit += k ? angka(k.value) : 0;
        });

        var totalDebit = document.getElementById('total-debit');
        var totalKredit = document.getElementById('total-kredit');
        if (totalDebit) {
            totalDebit.textContent = debit.toLocaleString('id-ID');
        }
        if (totalKredit) {
            totalKredit.textContent = kredit.toLocaleString('id-ID');
        }

        var chip = document.getElementById('balance-chip');
        if (!chip) {
            return;
        }

        var selisih = debit - kredit;
        if (debit === 0 && kredit === 0) {
            chip.dataset.state = 'empty';
            chip.innerHTML = '<span>Belum ada nilai</span>';
        } else if (Math.abs(selisih) < 0.005) {
            chip.dataset.state = 'balanced';
            chip.innerHTML = '<span>Seimbang · Rp ' + debit.toLocaleString('id-ID') + '</span>';
        } else {
            chip.dataset.state = 'unbalanced';
            chip.innerHTML = '<span>Selisih Rp ' + Math.abs(selisih).toLocaleString('id-ID') + '</span>';
        }
    }

    function bindRow(row) {
        var debit = row.querySelector('input[name$="[debit]"]');
        var kredit = row.querySelector('input[name$="[kredit]"]');

        [debit, kredit].forEach(function (input) {
            if (!input) {
                return;
            }
            input.value = format(input.value);
            input.addEventListener('input', function () {
                input.value = format(input.value);
                if (angka(input.value) > 0) {
                    var other = input === debit ? kredit : debit;
                    if (other) {
                        other.value = '';
                    }
                }
                recalc();
            });
        });

        var more = row.querySelector('[data-line-more]');
        if (more) {
            more.addEventListener('click', function () {
                var dimensi = dimensiRowOf(row);
                if (!dimensi) {
                    return;
                }
                dimensi.hidden = !dimensi.hidden;
                more.classList.toggle('is-active', !dimensi.hidden);
            });
        }

        var remove = row.querySelector('[data-line-remove]');
        if (remove) {
            remove.addEventListener('click', function () {
                if (rows().length <= 1) {
                    return;
                }
                var dimensi = dimensiRowOf(row);
                if (dimensi) {
                    dimensi.remove();
                }
                row.remove();
                reindex();
                recalc();
            });
        }
    }

    function addRow() {
        if (!template) {
            return;
        }
        var fragment = template.content.cloneNode(true);
        var newRows = fragment.querySelectorAll('tr');
        newRows.forEach(function (row) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/__INDEX__|lines\[\d+\]/g, 'lines[' + counter + ']');
            });
        });
        counter += 1;
        tbody.appendChild(fragment);

        var added = rows();
        var row = added[added.length - 1];
        bindRow(row);
        reindex();
        recalc();

        var select = row.querySelector('select');
        if (select) {
            select.focus();
        }
    }

    // inisialisasi baris yang sudah ada
    rows().forEach(function (row) {
        var select = row.querySelector('select[name$="[coa_id]"]');
        if (select && select.dataset.selected) {
            select.value = select.dataset.selected;
        }
        bindRow(row);
    });

    var addButton = document.querySelector('[data-line-add]');
    if (addButton) {
        addButton.addEventListener('click', function (event) {
            event.preventDefault();
            addRow();
        });
    }

    // Enter pada baris terakhir menambah baris baru
    tbody.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        var row = event.target.closest('tr[data-line]');
        if (!row) {
            return;
        }
        event.preventDefault();
        var all = rows();
        if (all.indexOf(row) === all.length - 1) {
            addRow();
        }
    });

    form.addEventListener('submit', function (event) {
        var debit = angka(document.getElementById('total-debit') ? document.getElementById('total-debit').textContent : '0');
        var kredit = angka(document.getElementById('total-kredit') ? document.getElementById('total-kredit').textContent : '0');

        if (debit > 0 && Math.abs(debit - kredit) > 0.005) {
            event.preventDefault();
            window.alert('Jurnal belum seimbang. Selisih Rp ' + Math.abs(debit - kredit).toLocaleString('id-ID') + '.');
            return;
        }

        // kirim nilai tanpa pemisah ribuan
        tbody.querySelectorAll('input.money').forEach(function (input) {
            input.value = String(angka(input.value));
        });
    });

    recalc();
})();
