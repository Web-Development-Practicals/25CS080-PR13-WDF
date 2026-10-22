<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - PHP JSON API CRUD</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; color: #212529; }
    header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; background:#343a40; padding:12px 20px; color:#fff; border-radius:6px; }
    header h2 { margin:0; }
    nav ul { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:15px; }
    nav a { color:#fff; text-decoration:none; font-weight:bold; }
    .container { margin-top:20px; display:flex; align-items:flex-start; gap:20px; }
    .card { background:#fff; padding:20px; border-radius:6px; box-shadow:0 2px 6px #00000012; }
    .form-card { flex:1; min-width:240px; }
    .table-card { flex:2; min-width:0; overflow-x:auto; }
    form input, form button { width:100%; padding:10px; margin-bottom:10px; }
    button { background:#218838; color:white; border:0; cursor:pointer; border-radius:4px; font-weight:bold; padding:8px 10px; }
    button:disabled { opacity:.6; cursor:wait; }
    button.btn-danger { background:#dc3545; }
    button.btn-edit { background:#ffc107; color:#212529; }
    button.btn-cancel { background:#6c757d; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { border:1px solid #ddd; padding:10px; text-align:left; }
    th { background:#007bff; color:white; }
    td.actions { white-space:nowrap; }
    td.actions button { margin-right:5px; }
    .message { margin-top:10px; overflow-wrap:anywhere; }
    .error { color:#b02a37; }
    .success { color:#146c43; }
    @media (max-width:800px) { .container { flex-direction:column; } .form-card,.table-card { width:100%; } }
  </style>
</head>
<body>
<header>
  <h2>Admin Dashboard</h2>
  <nav><ul id="dynamicMenu"></ul></nav>
  <div><label for="roleSelector">Active Role:</label>
    <select id="roleSelector"><option value="user">User</option><option value="editor">Editor</option><option value="admin" selected>Admin</option></select>
  </div>
</header>
<div class="container">
  <section class="card form-card">
    <h3 id="formTitle">Add Item</h3>
    <form id="itemForm">
      <input type="hidden" id="itemId">
      <input type="text" id="itemName" placeholder="Item Name" maxlength="150" required>
      <input type="text" id="itemCategory" placeholder="Category" maxlength="100" required>
      <input type="number" id="itemPrice" placeholder="Price" min="0" max="99999999.99" step="0.01" required>
      <button type="submit" id="submitBtn">Save Item</button>
      <button type="button" id="cancelBtn" class="btn-cancel" hidden>Cancel</button>
    </form>
    <p id="message" class="message" role="status" aria-live="polite"></p>
  </section>
  <section class="card table-card" id="inventory">
    <h3>Inventory List</h3>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Actions</th></tr></thead>
      <tbody id="itemsTableBody"><tr><td colspan="5">Loading items...</td></tr></tbody>
    </table>
  </section>
</div>
<script>
'use strict';
const API_URL = 'api.php';
const roleSelector = document.getElementById('roleSelector');
const menu = document.getElementById('dynamicMenu');
const form = document.getElementById('itemForm');
const tbody = document.getElementById('itemsTableBody');
const message = document.getElementById('message');
let items = [];

function showMessage(text, type = '') {
  message.textContent = text;
  message.className = 'message' + (type ? ' ' + type : '');
}

async function apiRequest(action, options = {}) {
  const url = `${API_URL}?action=${encodeURIComponent(action)}&role=${encodeURIComponent(roleSelector.value)}`;
  let response;
  try {
    response = await fetch(url, options);
  } catch (error) {
    throw new Error('Could not connect to the server. Start Apache/MySQL and open this page through localhost.');
  }
  const result = await response.json().catch(() => { throw new Error('Server returned invalid JSON. Check PHP errors.'); });
  if (!response.ok || result.status !== 'success') throw new Error(result.message || `Request failed (${response.status}).`);
  return result;
}

async function loadMenu() {
  menu.replaceChildren();
  try {
    const result = await apiRequest('get_menu');
    result.data.forEach(item => {
      const li = document.createElement('li');
      const link = document.createElement('a');
      link.textContent = item.title;
      link.href = item.link;
      li.appendChild(link);
      menu.appendChild(li);
    });
  } catch (error) { showMessage(error.message, 'error'); }
}

async function loadItems() {
  tbody.replaceChildren();
  try {
    const result = await apiRequest('read');
    items = result.data;
    if (!items.length) {
      const row = tbody.insertRow();
      const cell = row.insertCell(); cell.colSpan = 5; cell.textContent = 'No items found.';
      return;
    }
    items.forEach(item => {
      const row = tbody.insertRow();
      [item.id, item.name, item.category, Number(item.price).toFixed(2)].forEach(value => {
        const cell = row.insertCell(); cell.textContent = value;
      });
      const actions = row.insertCell(); actions.className = 'actions';
      if (roleSelector.value === 'admin' || roleSelector.value === 'editor') {
        const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn-edit'; edit.textContent = 'Edit';
        edit.addEventListener('click', () => editItem(item.id)); actions.appendChild(edit);
      }
      if (roleSelector.value === 'admin') {
        const del = document.createElement('button'); del.type = 'button'; del.className = 'btn-danger'; del.textContent = 'Delete';
        del.addEventListener('click', () => deleteItem(item.id)); actions.appendChild(del);
      }
    });
  } catch (error) {
    const row = tbody.insertRow(); const cell = row.insertCell(); cell.colSpan = 5;
    cell.textContent = 'Unable to load items: ' + error.message;
  }
}

async function saveItem(event) {
  event.preventDefault();
  const id = document.getElementById('itemId').value;
  const name = document.getElementById('itemName').value.trim();
  const category = document.getElementById('itemCategory').value.trim();
  const priceText = document.getElementById('itemPrice').value;
  const price = Number(priceText);
  if (roleSelector.value === 'user') { showMessage('Users cannot add or update items.', 'error'); return; }
  if (!name || !category || priceText === '' || !Number.isFinite(price) || price < 0) {
    showMessage('Please enter a valid name, category, and non-negative price.', 'error'); return;
  }
  const payload = { name, category, price };
  const action = id ? 'update' : 'create';
  if (id) payload.id = Number(id);
  const submit = document.getElementById('submitBtn'); submit.disabled = true;
  try {
    const result = await apiRequest(action, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
    resetForm(); showMessage(result.message, 'success'); await loadItems();
  } catch (error) { showMessage(error.message, 'error'); }
  finally { submit.disabled = false; }
}

function editItem(id) {
  const item = items.find(value => Number(value.id) === Number(id));
  if (!item) { showMessage('Item not found. Reload the list and try again.', 'error'); return; }
  document.getElementById('itemId').value = item.id;
  document.getElementById('itemName').value = item.name;
  document.getElementById('itemCategory').value = item.category;
  document.getElementById('itemPrice').value = item.price;
  document.getElementById('formTitle').textContent = 'Edit Item';
  document.getElementById('submitBtn').textContent = 'Update Item';
  document.getElementById('cancelBtn').hidden = false;
  showMessage('');
  document.getElementById('itemName').focus();
}

async function deleteItem(id) {
  if (roleSelector.value !== 'admin') { showMessage('Only admins can delete items.', 'error'); return; }
  if (!confirm('Are you sure you want to delete this item?')) return;
  try {
    const result = await apiRequest('delete', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:Number(id)}) });
    showMessage(result.message, 'success'); await loadItems();
  } catch (error) { showMessage(error.message, 'error'); }
}

function resetForm() {
  form.reset();
  document.getElementById('itemId').value = '';
  document.getElementById('formTitle').textContent = 'Add Item';
  document.getElementById('submitBtn').textContent = 'Save Item';
  document.getElementById('cancelBtn').hidden = true;
}

roleSelector.addEventListener('change', async () => { resetForm(); await loadMenu(); await loadItems(); });
form.addEventListener('submit', saveItem);
document.getElementById('cancelBtn').addEventListener('click', resetForm);
loadMenu();
loadItems();
</script>
</body>
</html>
