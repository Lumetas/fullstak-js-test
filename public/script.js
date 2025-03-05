const tableBody = document.getElementById('table-body');
const searchInput = document.getElementById('search');
let items = [];
let selectedItems = [];
let sortedItems = [];
let currentPage = 0;
const pageSize = 20;
let ids;
let isDragging = false;
let dragStartIndex;
let totalItems = 0;
let isSearching = false;

// Загрузка данных с сервера
async function loadItems(start, end, search = '') {
    const response = await fetch(`/api/items?search=${search}&start=${start}&end=${end}`);
    const data = await response.json();
    totalItems = data.total;

    return [data.items, data.ids];
}

// Отображение данных в таблице
function renderItems(itemsToRender, ids) {
    tableBody.innerHTML = ''; // Очищаем таблицу перед рендерингом
    for (i = 0; i < itemsToRender.length; i++) {
        // console.log(i);
        item = itemsToRender[i];
        id = ids[i];
        const row = document.createElement('tr');
        row.setAttribute('data-id', id);
        row.setAttribute('draggable', true);

        row.addEventListener('dragstart', (e) => {
            isDragging = true;
            dragStartIndex = Number(e.srcElement.getAttribute('data-id'));

            console.log('startdrag');
        });

        row.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (isDragging) {
                const dragOverIndex = Number(row.getAttribute('data-id'));
                if (dragStartIndex !== dragOverIndex) {
                    swapItems(dragStartIndex, dragOverIndex);
                    dragStartIndex = dragOverIndex;
                    // console.log(e.srcElement);
                }
                // console.log(dragStartIndex, dragOverIndex)
            }
        });

        row.addEventListener('dragend', (e) => {

            isDragging = false;
            console.log(e.srcElement);
            
        });

        const checkboxCell = document.createElement('td');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.setAttribute("data-id", id);
        // console.log(id);
        checkbox.checked = selectedItems.includes(id);

        checkbox.addEventListener('change', (e) => { toggleSelection(Number(e.srcElement.getAttribute('data-id'))); });
        checkboxCell.appendChild(checkbox);
        row.appendChild(checkboxCell);

        const itemCell = document.createElement('td');
        itemCell.textContent = item;
        row.appendChild(itemCell);

        tableBody.appendChild(row);
    };
}

// Переключение выбора элемента
function toggleSelection(item) {
    if (selectedItems.includes(item)) {
        selectedItems = selectedItems.filter(i => i !== item);
    } else {
        selectedItems.push(item);
    }
    saveState();
}

// Сохранение состояния на сервере
async function saveState(fromIndex, toIndex) {
    await fetch('/api/state', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ selectedItems, fromIndex, toIndex })
    });
}

// Поиск элементов
searchInput.addEventListener('input', async () => {
    const searchValue = searchInput.value;
    isSearching = searchValue.length > 0;

    if (isSearching) {
        currentPage = 0;
        items = [];
        tableBody.innerHTML = '';
        const response = await loadItems(0, pageSize, searchValue);
        items = response[0];
        ids = response[1];
        renderItems(response[0], response[1]);
    } else {
        currentPage = 0;
        items = [];
        tableBody.innerHTML = '';
        const response = await loadItems(0, pageSize);
        items = response[0];
        renderItems(response[0], response[1]);
    }
});

// Подгрузка данных при скролле
window.addEventListener('scroll', async () => {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight) {
        if (items.length >= totalItems) return; // Не загружать, если все элементы уже загружены
        currentPage++;
        const start = currentPage * pageSize;
        const end = start + pageSize;
        const response = await loadItems(start, end, searchInput.value);
        items = [...items, ...response[0]];
        ids = [...ids, ...response[1]];
        renderItems(items, ids);
    }
});

// Drag & Drop: перестановка элементов
function swapItems(fromIndex, toIndex) {
    v_fromIndex = ids.indexOf(fromIndex);
    v_toIndex = ids.indexOf(toIndex);
    console.log(v_fromIndex, v_toIndex)
    const temp = items[v_fromIndex];
    items[v_fromIndex] = items[v_toIndex];
    items[v_toIndex] = temp;

    
    

    // Сохраняем состояние на сервере
    saveState(fromIndex, toIndex);

    // Обновляем отображение
    renderItems(items, ids);
}

// Загрузка состояния при старте
async function loadState() {
    const response = await fetch('/api/state');
    const data = await response.json();
    selectedItems = data.selectedItems;
    sortedItems = data.sortedItems;
    items = sortedItems;
    ids = data.ids;
}

// Инициализация
(async function init() {
    await loadState();
    const response = await loadItems(0, pageSize);
    renderItems(response[0], response[1]);
})();