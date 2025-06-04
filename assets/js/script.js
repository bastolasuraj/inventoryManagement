const API_URL = ''; // empty since we will call e.g. "inventory.php" and "commands.php" in the same folder

let inventoryData = []; // must be global so auto-fill can access it

// ----- Inventory Fetch + Delete -----
async function fetchInventory() {
    try {
        const res = await fetch(API_URL + 'inventory.php');
        inventoryData = await res.json();  // <-- assign here!
        const tbody = document.querySelector('#inventoryTable tbody');
        tbody.innerHTML = '';
        inventoryData.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
          <td>${item.partNumber}</td>
          <td>${item.description || ''}</td>
          <td>${item.quantityOnHand}</td>
          <!-- Delete button goes here if needed -->
        `;
            tbody.appendChild(tr);
        });
    } catch(err) {
        console.error('Failed to load inventory:', err);
    }
}

// async function deleteInventoryPart(partNumber) {
//    if (!confirm('Are you sure you want to delete part ' + partNumber + '?')) return;
//    try {
//        const res = await fetch(API_URL + `inventory.php?partNumber=${encodeURIComponent(partNumber)}`, {
//            method: 'DELETE'
//        });
//        const data = await res.json();
//        if (res.ok) {
//            fetchInventory();
//            fetchCommands();
//        } else {
//            alert(data.message || 'Failed to delete part');
//        }
//    } catch(err) {
//        console.error('Delete error:', err);
//        alert('Error deleting part');
//    }
//}

// ----- Commands Fetch + Pagination -----
const COMMANDS_PER_PAGE = 5;
let currentPage = 1;
let commandsData = [];

async function fetchCommands() {
    try {
        const res = await fetch(API_URL + 'commands.php');
        commandsData = await res.json();
        renderCommandsPage(currentPage);
    } catch(err) {
        console.error('Failed to load commands:', err);
    }
}

function renderCommandsPage(page) {
    // const formattedTs = new Date(cmd.timestamp).toLocaleString();
    const tbody = document.querySelector('#commandsTable tbody');
    tbody.innerHTML = '';

    // Sort latest first
    const commandsSorted = commandsData.slice().reverse();

    const startIndex = (page - 1) * COMMANDS_PER_PAGE;
    const pageCommands = commandsSorted.slice(startIndex, startIndex + COMMANDS_PER_PAGE);

    pageCommands.forEach(cmd => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td>${cmd.partNumber}</td>
        <td>${cmd.description || ''}</td>
        <td>${cmd.quantityChange}</td>
        <td>${cmd.remarks || ''}</td>
        <td>${cmd.yard}</td>
        <td>${cmd.user}</td>
        <td>${new Date(cmd.timestamp).toLocaleString()}</td>
      `;
        tbody.appendChild(tr);
    });

    document.getElementById('prevPage').disabled = page <= 1;
    document.getElementById('nextPage').disabled = startIndex + COMMANDS_PER_PAGE >= commandsSorted.length;
    document.getElementById('pageInfo').textContent = `Page ${page}`;
}

document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage--;
        renderCommandsPage(currentPage);
    }
});
document.getElementById('nextPage').addEventListener('click', () => {
    if (currentPage * COMMANDS_PER_PAGE < commandsData.length) {
        currentPage++;
        renderCommandsPage(currentPage);
    }
});

// ----- Auto-fill Part Name (on Part Number input) -----
document.getElementById('cmdPartNumber').addEventListener('input', e => {
    const partNumber = e.target.value.trim();
    const existingItem = inventoryData.find(i => i.partNumber === partNumber);
    const partNameInput = document.getElementById('cmdPartName');

    if (existingItem) {
        // If the part number exists, auto-fill and disable Part Name
        partNameInput.value    = existingItem.description;
        partNameInput.disabled = true;
    } else {
        // Otherwise, clear and re-enable Part Name
        partNameInput.value    = '';
        partNameInput.disabled = false;
    }
});

// ----- Send New Command (Add / Remove) -----
document.getElementById('btnAdd').addEventListener('click', () => {
    submitCommand(1);
});
document.getElementById('btnRemove').addEventListener('click', () => {
    submitCommand(-1);
});

function submitCommand(direction) {
    const partNumber = document.getElementById('cmdPartNumber').value.trim();
    const partName   = document.getElementById('cmdPartName').value.trim();
    const qtyChange  = parseInt(document.getElementById('cmdQuantityChange').value, 10);
    const remarks    = document.getElementById('cmdRemarks').value.trim();
    const yard       = document.getElementById('cmdYard').value.trim();
    const user       = document.getElementById('cmdUser').value.trim();

    if (!partNumber || !yard || !user || isNaN(qtyChange)) {
        alert('Please fill all required fields.');
        return;
    }

    const payload = {
        partNumber,
        description: partName,
        quantityChange: direction * Math.abs(qtyChange),
        remarks,
        yard,
        user,
        timestamp: Date.now()
    };

    fetch(API_URL + 'commands.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => {
            if (!res.ok) throw new Error('Failed to add command');
            return res.json();
        })
        .then(() => {
            fetchInventory();
            fetchCommands();
            document.getElementById('commandForm').reset();
        })
        .catch(err => {
            console.error(err);
            alert('Failed to add command. Check console.');
        });
}

// ----- Initial load -----
(async function init() {
    await fetchInventory();
    await fetchCommands();
})();
