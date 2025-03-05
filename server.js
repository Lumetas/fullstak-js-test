const express = require('express');
const path = require('path');
const app = express();
const port = 5000;

app.use(express.static(path.join(__dirname, 'public')));
app.use(express.json());

let state = {
    selectedItems: [],
    sortedItems: Array.from({ length: 1000000 }, (_, i) => i + 1) // Исходный порядок
};

// API для получения элементов
app.get('/api/items', (req, res) => {
    const { search, start = 0, end = 20 } = req.query;
    let items = state.sortedItems;
    let newItems = [];
    let ids = [];

    if (search) {
        for (i = 0 ; i < items.length; i++){
            if (String(items[i]).includes(search)){
                newItems.push(items[i]);
                ids.push(i);
            }
        }
    } else {
        for (i = 0 ; i < items.length; i++){
                newItems.push(items[i]);
                ids.push(i);
        }
    }

    res.json({
        ids: ids.slice(start, end),
        items: newItems.slice(start, end),
        total: items.length
    });
});

// API для сохранения состояния
app.post('/api/state', (req, res) => {
    const { selectedItems, fromIndex, toIndex } = req.body;

    // Обновляем выбранные элементы
    state.selectedItems = selectedItems;

    // Если есть информация о перемещении, обновляем sortedItems
    if (fromIndex !== undefined && toIndex !== undefined) {
        const [movedItem] = state.sortedItems.splice(fromIndex, 1);
        state.sortedItems.splice(toIndex, 0, movedItem);
    }

    res.json({ status: 'success' });
});

// Загрузка состояния при старте
app.get('/api/state', (req, res) => {
    let ids = [];
    for (i = 0; i < state.sortedItems.length; i++){
        ids.push(i);
    }
    res.json({
        ids: ids.slice(0, 20),
        sortedItems: state.sortedItems.slice(0, 20),
        selectedItems: state.selectedItems
    });

});

app.listen(port, () => {
    console.log(`Server running on http://localhost:${port}`);
});