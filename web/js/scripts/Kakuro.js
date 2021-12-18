import React from 'react';
import Cell from './Cell.js';
import KakuroControls from './KakuroControls.js';
import KakuroTitle from './KakuroTitle.js';
import KakuroMessages from './KakuroMessages.js';
import {GridHelper} from './GridHelper.js';
import {Reducer} from './Reducer.js';

var gridId = document.getElementById("content").dataset.id;

export default class Kakuro extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            name: '',
            savedGameName: '',
            cells: [],
            height: 0,
            width: 0,
            active_row: 1,
            active_col: 1,
            solved: false,
            saved_states: [],
            grids: [],
            gridId: 0,
            gridStatus: '', // success, error
            messages: [],
            reductionLevel: 0,
            changedStrips: [],
            runLoop: true,
        };

        this.strips = {};
        this.loopInterval = null;

        this.getGames = this.getGames.bind(this);
        this.getGrid = this.getGrid.bind(this);
        this.saveState = this.saveState.bind(this);
        this.restoreSavedState = this.restoreSavedState.bind(this);
        this.saveChoices = this.saveChoices.bind(this);
        this.loadSavedGame = this.loadSavedGame.bind(this);
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
        this.startLoopInterval = this.startLoopInterval.bind(this);
        this.clearLoopInterval = this.clearLoopInterval.bind(this);
    }

    componentDidMount() {
// console.log(Reducer.possibleValues(26,4,[2]));
// console.log(Reducer.possibleValues(37,7,[5]));
// console.log(Reducer.possibleValues(7,3));
// console.log(Reducer.possibleValues(27,5,[2,9]));
// console.log(Reducer.possibleValues(3,1,[3]));
// console.log(Reducer.intersection([[3,1],[3]]));
// console.log(Reducer.sortByLength([[3,1],[4,5,6],[3],[9]]));
// console.log(Reducer.isPossible(3,[[2,1],[1]],[[1,2]]));
// console.log(Reducer.isPossible(5,[[2,1,3,4],[1]],[[1,4],[2,3]]));
// console.log(Reducer.isPossible(23,[[6,8,9],[6,8],[6]],[[6,8,9]]));
// console.log(Reducer.isPossible(23,[[6,8,7],[6,8],[6]],[[6,8,9]]));
// console.log(Reducer.isPossible(18,[[1,2,3],[1,2,4],[3,7,8],[6,8],[1,2,4]],[[1,2,3,4,8],[1,2,3,5,7],[1,2,4,5,6]]));
// console.log(Reducer.isPossible(18,[[1,2,3],[1,2,4],[3,7,8],[8],[1,2,4]],[[1,2,3,4,8],[1,2,3,5,7],[1,2,4,5,6]]));
// console.log(Reducer.isPossible(18,[[1,2,3],[1,2,4],[3,7,8],[6],[1,2,4]],[[1,2,3,4,8],[1,2,3,5,7],[1,2,4,5,6]]));
// console.log(Reducer.isPossible(27,[[4,5],[8,9],[1,2,3],[1,2,4],[7,8,9]],[[1,2,7,8,9],[1,3,6,8,9],[1,4,5,8,9],[1,4,6,7,9],[1,5,6,7,8],[2,3,5,8,9],[2,3,6,7,9],[2,4,5,7,9],[2,4,6,7,8],[3,4,5,6,9],[3,4,5,7,8]]));
// console.log(Reducer.isPossible(34, [[3],[1,2],[6,8,9],[1,3,4,5,6,8,9],[8,9],[7]], [[1,3,6,7,8,9],[1,4,5,7,8,9],[2,3,5,7,8,9],[2,4,5,6,8,9],[3,4,5,6,7,9]]));
// console.log(Reducer.isPossible(34, [[1],[1,2],[6,8,9],[1,3,4,5,6,8,9],[8,9],[7]], [[1,3,6,7,8,9],[1,4,5,7,8,9],[2,3,5,7,8,9],[2,4,5,6,8,9],[3,4,5,6,7,9]]));
// console.log(Reducer.isPossible(33, [[3],[3,4,5,6,8,9],[3,4,5,6,7,8,9],[7,8,9],[3,4,5,6,7,8,9]], [[3,6,7,8,9],[4,5,7,8,9]]));
// console.log(Reducer.isPossible(15, [[7],[7,9]], [[6,9],[7,8]]));
// console.log(Reducer.isPossible(30, [[6],[1,2,3,4,5,6],[4,5,6,7,8,9],[6,7,8,9],[2,3,6,7]], [[1,5,7,8,9],[2,4,7,8,9],[2,5,6,8,9],[3,4,6,8,9],[3,5,6,7,9],[4,5,6,7,8]]));
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
        window.location.href = 'http://kak.uro/app_dev.php/grid/' + id;
    }

    getGrid(id) {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + id
        ).then(data => {
            var processed = GridHelper.processData(data.cells, data.height, data.width, this.state.active_row, this.state.active_col);
            var cells = processed.cells;
            // this.strips = processed.strips;
            this.setState({cells: cells, height: data.height, width: data.width, name: data.name, gridId: id}, this.reduce3);
            this.saveState();
        });
    }

    startLoopInterval() {
        let k = 0;
console.log('startLoopInterval', this.state.runLoop)
        this.loopInterval = setInterval(() => {
            if (this.state.runLoop) {
                if (k++ < 500) {
                    console.log('startLoopInterval line 112', this.state.changedStrips, k, this.state.reductionLevel);
                    this.reduce3(this.state.changedStrips);}
                }
            }
        , 200);

    }

    clearLoopInterval() {
        clearInterval(this.loopInterval);
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
            "http://kak.uro/app_dev.php/api/save-design",
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

    reduce3(cs = [], cellIndexToStart = -1, lev = null) {
        if (lev === null) {
            lev = this.state.reductionLevel;
        }
        let vals = Reducer.reduce(lev, this.state.cells, cellIndexToStart, cs, this.strips, this.state.height, this.state.width);
        let { cells, strips, level, changedStrips, msg } = vals;
        console.log('line 194: ', level, this.state.reductionLevel, changedStrips, this.strips);
        let runLoop = this.state.runLoop;
        if (!level || (level > 70 && level !== this.state.reductionLevel)) { 
            console.log('quitting. level: ', level, this.state.reductionLevel, changedStrips);
            runLoop = false; 
            this.clearLoopInterval();
        }
        this.strips = strips;
        this.setState({ cells: cells, reductionLevel: level, messages: msg, changedStrips, runLoop });
    }

    reduce2(cellIndexToStart, lev = null) {
        if (lev === null) {
            lev = this.state.reductionLevel;
        }
        let vals = Reducer.reduce(lev, this.state.cells, cellIndexToStart, [], this.strips, this.state.height, this.state.width);
        let { cells, strips, level, msg } = vals;
        console.log(vals);
        this.strips = strips;
        this.setState({ cells: cells, reductionLevel: level, messages: msg, changedStrips: [], runLoop: false });
    }

    reduce(level) {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/get-choices",
            {
                grid_id: this.state.gridId,
                cells: cells,
                active_cell_idx: this.state.active_row * this.state.width + this.state.active_col,
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

    updateChoices(cells) {
        var processed = GridHelper.checkStrips(cells, this.strips);
        let solved = this.state.solved;
        this.strips = processed.strips;
        cells = processed.cells;
        if (processed.status === 'success') {
            solved = true;
        }
        this.setState({cells: cells, gridStatus: processed.status, solved});
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
            if (cell.is_data) {
                cell.choices = [];
                cell.strips.forEach(stripIdx => {
                    if (stripIdx in this.strips && 'changed' in this.strips[stripIdx]) {
                        this.strips[stripIdx].changed = true;
                        this.strips[stripIdx].unknown = this.strips[stripIdx].length;
                    }
                });
            }
        });

        this.setState({ cells, reductionLevel: 0, messages: ['game reset'] }, this.reduce3);
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
            this.updateChoices(cells);
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
        if (keyCode === 72) { // h -- hint (one step)
            this.reduce2(this.state.active_row * this.state.width + this.state.active_col);
        }
        if (keyCode === 80) { // p
            this.reduce2(20);
        }
        if (keyCode === 82) { // r
            this.reduce2(30);
        }
        if (keyCode === 65) { // a
            this.reduce3([], this.state.active_row * this.state.width + this.state.active_col, 10);
        }
        if (keyCode === 66) { // b
            this.reduce2(50);
        }
        if (keyCode === 69) { // e
            this.clearChoices();
        }
        if (keyCode === 87) { // w
            this.setState({ runLoop: false });
            this.clearLoopInterval();
        }
        if (keyCode === 89) { // y
            this.setState({ runLoop: true }, this.startLoopInterval);
        }
        if (keyCode === 90) { // z
            this.reduce3([], this.state.active_row * this.state.width + this.state.active_col, 20);
        }
        if (keyCode === 88) { // x
            this.reduce3([], this.state.active_row * this.state.width + this.state.active_col, 30);
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
            return (
                <Cell 
                    cell={cell}
                    solved={this.state.solved}
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
                        savedGameName={this.state.savedGameName}
                        gridName={this.state.name}
                        save={this.saveChoices}
                        grids={this.state.grids}
                        getGrid={this.loadGridUrl}
                        selectedGrid={gridId}
                        selectedGridName={this.state.name}
                        showSave={this.state.solved}
                        showDesign={true}
                        showDesignBySum={true}
                    />
                </div>
                <div className="status-box">
                    {this.state.gridStatus}
                </div>
                <KakuroMessages messages={this.state.messages} />
            </div>
        );
    }
}

