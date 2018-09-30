import React from 'react';
import Cell from './Cell.jsx';
import KakuroControls from './KakuroControls.jsx';

export default class Grid extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            name: vars.grid_name,
            savedGameName: '',
            cells: [],
            height: 0,
            width: 0,
            active_row: -1,
            active_col: -1,
            solved: false,
            saved_states: [],
            grids: [{name: 0, label:""}, {name: 4, label:"shit"}, {name: 3, label:"more shit"}],
            gridId: 1,
        };
        this.getGames = this.getGames.bind(this);
        this.getGrid = this.getGrid.bind(this);
        this.saveState = this.saveState.bind(this);
        this.restoreSavedState = this.restoreSavedState.bind(this);
        this.saveChoices = this.saveChoices.bind(this);
        this.loadSavedGame = this.loadSavedGame.bind(this);
        this.simpleReduce = this.simpleReduce.bind(this);
        this.advancedReduce = this.advancedReduce.bind(this);
        this.giveHint = this.giveHint.bind(this);
        this.clearChoices = this.clearChoices.bind(this);
        this.clearAllChoices = this.clearAllChoices.bind(this);
        this.reduce = this.reduce.bind(this);
        this.processNewData = this.processNewData.bind(this);
        this.setActive = this.setActive.bind(this);
        this.moveActive = this.moveActive.bind(this);
        this.handleChangedCell = this.handleChangedCell.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleKey = this.handleKey.bind(this);
        this.checkAnswer = this.checkAnswer.bind(this);
    }

    componentDidMount() {
        this.getGames();
        // this.getGrid(1);
    }

    getGames() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/games"
        ).then(data => {
            this.setState({grids: data.games});
            this.getGrid(data.games[0]['name']);
        });
    }

    getGrid(id) {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + id
        ).then(data => {
            var cells = this.processNewData(data.cells, data.height, data.width);
            this.setState({cells: cells, height: data.height, width: data.width, name: data.name, gridId: id});
            this.saveState();
        });
    }

    saveState() {
        var cells = $.extend(true, [], this.state.cells);
        this.state.saved_states.push(cells);
    }    

    restoreSavedState() {
        var cells = this.state.saved_states.pop();
        if (!cells) {
            return;
        }
        var active_row = -1;
        var active_col = -1;
        cells.forEach((cell, idx) => {
            if(cell.active) {
                active_row = cell.row;
                active_col = cell.col;
            }
        });
        this.setState({cells: cells, active_row: active_row, active_col: active_col});
    }

    saveChoices(name) {
        var cells = JSON.stringify(this.state.cells);
        name = name || null;
        return $.post(
            "http://kak.uro/app_dev.php/api/save-choices",
            {
                grid_id: this.state.gridId,
                saved_grid_name: name,
                cells: cells
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        );
    }

    loadSavedGame() {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/load-choices",
            {
                saved_grid_id: 6
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        ).then(data => {
            cells = this.processNewData(data.cells, data.height, data.width);
            this.setState({ cells: cells, height: data.height, width: data.width, savedGameName: data.name });
            this.saveState();
        });
    }

    reduce(level) {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/get-choices",
            {
                grid_id: this.state.gridId,
                cells: cells,
                level: level,
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        ).then(data => {
            this.setState({ cells: data.cells });
        });
    }

    simpleReduce(fullRoutine) {
        this.reduce(false);
    }

    advancedReduce() {
        this.reduce(true);
    }

    giveHint() {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/get-hint",
            {
                grid_id: this.state.gridId,
                cells: cells,
            },
            function(resp) {
                if (resp.hint) {
                    alert(resp.hint);
                }
            },
            'json'
        ).then(data => {
            this.setState({ cells: data.cells });
        });
    }

    clearChoices() {
        var cells = this.state.cells;
        var idx = this.state.active_row * this.state.width + this.state.active_col;
        cells[idx].choices = [];

        this.setState({ cells: cells });
    }

    clearAllChoices() {
        var cells = this.state.cells;
        cells.forEach((cell, idx) => {
            cell.choices = [];
        });

        this.setState({ cells: cells });
    }

    processNewData(cells, height, width) {
        cells.forEach((cell, idx) => {
            cell.col = idx % width;
            cell.row = Math.floor(idx / width);
            if (cell.row == 0 || cell.col == 0) {
                cell.is_data = false;
                cell.display = cell.display || [0,0];
            }
            cell.active = cell.row === this.state.active_row && cell.col === this.state.active_col;
            cells[idx] = cell;
        });
        return cells;
    }

    setActive(row, col) {
        var fidx = this.state.active_row * this.state.width + this.state.active_col;
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        if (fidx >= 0) {
            cells[fidx].active = false;
        }
        cells[idx].active = true;
        this.saveState();
        this.setState({cells: cells, active_row: row, active_col: col});
    }

    moveActive(v,h, row, col) {
        if (typeof row === 'undefined') {
            row = this.state.active_row;
        }
        if (typeof col === 'undefined') {
            col = this.state.active_col;
        }
        var active_row = row + v;
        var active_col = col + h;
        if (active_row >= this.state.height) {
            active_row = 0;
        }
        if (active_row < 0) {
            active_row =  this.state.height - 1;
        }
        if (active_col >= this.state.width) {
            active_col = 0;
        }
        if (active_col < 0) {
            active_col =  this.state.width - 1;
        }

        if (!this.state.cells[active_row * this.state.width + active_col].is_data) {
            this.moveActive(v,h, active_row, active_col);
        } else {
            this.setActive(active_row, active_col);
        }
    }

    handleChangedCell(row, col, val) {
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        cells[idx].choices = val;
        this.setState({cells: cells});
    }

    handleKeyDown(event) {
        var key = parseInt(event.key);
        var idx = this.state.active_row * this.state.width + this.state.active_col;
        var cells = this.state.cells;
        var cell = cells[idx];
        if (key > 0) {
            var arr_pos = cell.choices.indexOf(key);
            if (arr_pos > -1) {
                cell.choices.splice(arr_pos, 1);
            } else {
                cell.choices.push(key);
            }
            cell.choices.sort();
            cell.display = cell.choices.join('');
            cells[idx] = cell;
            this.checkAnswer(cells);
            this.setState({cells: cells});
        } else {
            var keyCode = event.keyCode;
            this.handleKey(keyCode);
        }
    }

    handleKey(keyCode) {
        if (keyCode === 38) { // up
            this.moveActive(-1,0);
        }
        if (keyCode === 40) {
            this.moveActive(1,0);
        }
        if (keyCode === 37) {
            this.moveActive(0,-1);
        }
        if (keyCode === 39) {
            this.moveActive(0,1);
        }
        if (keyCode === 80) { // p
            this.reduce(2);
        }
        if (keyCode === 82) { // r
            this.reduce(3);
        }
        if (keyCode === 65) { // a
            this.reduce(4);
        }
        if (keyCode === 72) { // h -- hint (one step)
            this.reduce(1);
        }
        if (keyCode === 88) { // x
            this.clearChoices();
        }
        if (keyCode === 67) { // c
            this.clearAllChoices();
        }
        if (keyCode === 85) { // u
            this.restoreSavedState();
        }
        if (keyCode === 83) { // s
            this.saveChoices();
        }
        if (keyCode === 76) { // l
            this.loadSavedGame();
        }
    }

    checkAnswer(cells) {
        for (let cell of cells) {
            if(cell.is_data) {
                if (cell.choices.length !== 1) {
                    return false;
                }
            }
        };
        return $.post(
            "http://kak.uro/app_dev.php/api/check",
            {
                grid_name: this.state.name,
                cells: JSON.stringify(cells),
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        ).then(data => {
            if (data.isSolution) {
                this.setState({ solved: true });
            }
        });
    }

    render() {
        var cells = this.state.cells.map(function(cell, index) {
            cell.active = cell.row == this.state.active_row && cell.col == this.state.active_col;
            return <Cell 
                    cell={cell}
                    solved={this.state.solved}
                    key={index}
                    onClick={() => this.setActive(cell.row, cell.col)}
                    onChange={this.handleChangedCell}
                   />;
        }, this);
        var classes = "kakuro-grid col-md-8";
        if (this.state.solved) {
            classes = classes + ' grid-solved';
        }
        return (
            <div>
                <div className={classes} tabIndex="0" onKeyDown={this.handleKeyDown}>
                   {cells}
                </div>
                <div className="col-md-4">
                    <KakuroControls
                        savedGameName={this.state.savedGameName}
                        gridName={this.state.name}
                        save={this.saveChoices}
                        grids={this.state.grids}
                        getGrid={this.getGrid}
                    />
                </div>
            </div>
        );
    }
}

