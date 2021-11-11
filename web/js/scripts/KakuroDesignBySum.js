import React from 'react';
import Cell from './Cell.js';
import Design from './Design.js';
import KakuroTitle from './KakuroTitle.js';
import KakuroControls from './KakuroControls.js';
import {GridHelper} from './GridHelper.js';

var gridId = document.getElementById("content").dataset.id;

export default class KakuroDesignBySum extends Design {
    constructor(props) {
        super(props);
        this.state = {
            name: '',
            savedGameName: '',
            cells: [],
            height: 0,
            width: 0,
            active_row: -1,
            active_col: -1,
            active_idx: -1,
            solved: false,
            saved_states: [],
            grids: [{name: 0, label:""}, {name: 4, label:"stuff"}, {name: 3, label:"more stuff"}],
            gridId: 0,
            gridStatus: '', // success, error
            editing: false,
            editingIdx: 0, // which half of the display? 0|1
        };

        this.strips = [];

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
        this.updateChoices = this.updateChoices.bind(this);
        this.clearAllChoices = this.clearAllChoices.bind(this);
        this.reduce = this.reduce.bind(this);
        this.setActive = this.setActive.bind(this);
        this.moveActive = this.moveActive.bind(this);
        this.handleChangedCell = this.handleChangedCell.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleKey = this.handleKey.bind(this);
        this.checkAnswer = this.checkAnswer.bind(this);
    }

    componentDidMount() {
        this.getGames();
        if (gridId > 0) {
            this.getGrid(gridId);
        }
    }

    getGames() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/games"
        ).then(data => {
            this.setState({grids: data.games});
        });
    }

    loadGridUrl(id) {
        window.location.href = 'http://kak.uro/app_dev.php/grid/design-by-sum/' + id;
    }

    getGrid(id) {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + id
        ).then(data => {
            var processed = GridHelper.processData(data.cells, data.height, data.width, this.state.active_row, this.state.active_col);
            var cells = processed.cells;
            this.strips = processed.strips;
            this.setState({cells: cells, height: data.height, width: data.width, name: data.name, gridId: id});
            this.saveState();
        });
    }

    saveGame(name, asCopy) {
        if (!GridHelper.validGrid(this.state.cells, this.state.height, this.state.width)) {
            console.error('invalid for saving');
            console.error(GridHelper.message);
        }

        var cells = JSON.stringify(this.state.cells);
        name = name || null;
        return $.post(
            "http://kak.uro/app_dev.php/api/save-design-by-sums",
            {
                grid_id: this.state.gridId,
                height: this.state.height,
                width: this.state.width,
                name: name,
                cells: cells,
                asCopy: ~~asCopy,
            },
            function(resp) {
                if (asCopy) {
                    this.loadGridUrl(parseInt(resp.id));
                } else {
                    this.setState({gridName: resp.name, gridId: parseInt(resp.id)});
                }
            }.bind(this),
            'json'
        );
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
                active_idx = cell.idx;
            }
        });
        this.setState({cells: cells, active_row: active_row, active_col: active_col, active_idx: active_idx});
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
                saved_grid_id: 18
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        ).then(data => {
            this.updateChoices(data.cells);
            // var processed = GridHelper.processData(data.cells, data.height, data.width, this.state.active_row, this.state.active_col);
            // cells = processed.cells;
            // this.strips = processed.strips;
            // this.setState({ cells: cells, height: data.height, width: data.width, savedGameName: data.name });
            // this.saveState();
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
            if (!data.hasUniqueSolution) {
                alert('This game has more than one solution');
                console.log(data.solutions);
            }
            this.updateChoices(data.cells);
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

    updateChoices(cells) {
        // var processed = GridHelper.checkStrips(cells, this.strips);
        // var msg = '';
        // this.strips = processed.strips;
        // cells = processed.cells;
        this.setState({cells: cells});
        // this.setState({cells: cells}, async () => {console.log('123');});
    }

    clearChoices() {
        var cells = this.state.cells;
        var idx = this.state.active_row * this.state.width + this.state.active_col;
        cells[idx].choices = [];

        this.updateChoices(cells);
    }

    clearAllChoices() {
        var cells = this.state.cells;
        cells.forEach((cell, idx) => {
            cell.choices = [];
        });

        this.updateChoices(cells);
    }

    setActive(row, col) {
        var fidx = this.state.active_row * this.state.width + this.state.active_col;
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        if (fidx >= 0) {
            cells[fidx].active = false;
            cells[fidx].editing = false;
        }
        cells[idx].active = true;
        cells[idx].editing = this.state.editing; // does nothing?
        this.saveState();
        this.setState({cells: cells, active_row: row, active_col: col, editing: false, editingIdx: 0, active_idx: idx});
    }

    moveActive(v, h, row, col) {
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

        this.setActive(active_row, active_col);
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
        if (key >= 0) {
            if (cell.is_data) {
                return;
            }
            if (this.state.editing) {
                console.log(key, 'ok!', cell);
                let dsp = cell.display[this.state.editingIdx];
                console.log(dsp);
                dsp = dsp + key;
                console.log(dsp);
                cell.display[this.state.editingIdx] = dsp;
                cells[idx] = cell;
                this.setState({cells: cells}, () => {console.log(this.state.cells[idx].display)});
            }
        } else {
            var keyCode = event.keyCode;
            this.handleKey(keyCode);
        }
    }

    handleKey(keyCode) {
console.log('keyCode', keyCode);
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
        if (keyCode === 69) { // e
            this.editCell(0);
        }
        if (keyCode === 191 || keyCode === 220) { // slash or backslash
            this.editCell(1);
        }
        if (keyCode === 88) { // x
            this.setActiveCellVal('x');
        }
    }

    editCell(displayIdx) {
        let cells = this.state.cells;
        cells[this.state.active_idx].display[displayIdx] = '';
        this.setState({ editing: true, editingIdx: displayIdx, cells })
    }

    setActiveCellVal(val) {
        var idx = this.state.active_idx;
        var cells = this.state.cells;
        let cell = cells[idx];

        switch(val) {
            case 'x':
                cell.is_data = !cell.is_data;
                if (!cell.is_data) {
                    cell.display = [0,0];
                }
                break;
            default:
                break;
        }

        cells[idx] = cell;

        this.setState({ cells })
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
            cell.editing = this.state.editing && cell.active;
            cell.editing_right = this.state.editing && cell.active && this.state.editingIdx === 1;
            return (
                <Cell 
                    cell={cell}
                    solved={false}
                    key={index}
                    setActive={this.setActive}
                    onChange={this.handleChangedCell}
                />
            );
        }, this);
        var classes = "kakuro-grid col-md-8";
        if (this.state.solved) {
            classes = classes + ' grid-solved';
        }
        return (
            <div>
                <KakuroTitle title={this.state.name} />
                <div className={classes} tabIndex="0" onKeyDown={this.handleKeyDown}>
                   {cells}
                </div>
                <div className="col-md-4">
                    <KakuroControls
                        savedGameName={this.state.gridName}
                        height={this.state.height}
                        width={this.state.width}
                        selectedGrid={this.state.gridId}
                        save={this.saveGame}
                        delete={this.deleteGame}
                        grids={this.state.grids}
                        getGrid={this.loadGridUrl}
                        newGrid={this.newGrid}
                        selectedGridName={this.state.name}
                        createMode={true}
                        checkSolution={this.checkSolution}
                        showSave={true}
                        showDesign={true}
                        showPlay={true}
                        showDesignBySum={false}
                    />
                </div>
                <div className="status-box">
                    {this.state.gridStatus}
                </div>
            </div>
        );
    }
}

