import React from 'react';
import ReactDOM from 'react-dom';
import Cell from './Cell.jsx';
import KakuroControls from './KakuroControls.jsx';
import {GridHelper} from './GridHelper.js';

var gridId = document.getElementById("content").dataset.id;

export default class GridDesigner extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            grids: [],
            gridId: 0,
            gridName: '',
            cells: [],
            height: 4,
            width: 4,
            active_row: -1,
            active_col: -1,
        };

        this.strips = [];

        this.getGrid = this.getGrid.bind(this);
        this.saveGame = this.saveGame.bind(this);
        this.getGames = this.getGames.bind(this);
        this.setActive = this.setActive.bind(this);
        this.moveActive = this.moveActive.bind(this);
        this.handleKey = this.handleKey.bind(this);
        this.newGrid = this.newGrid.bind(this);
        this.setActiveCellVal = this.setActiveCellVal.bind(this);
        this.checkSolution = this.checkSolution.bind(this);
        this.removeRow = this.removeRow.bind(this);
        this.addRow = this.addRow.bind(this);
        this.removeCol = this.removeCol.bind(this);
        this.addCol = this.addCol.bind(this);
        
    }

    componentDidMount() {
        this.getGames();
        if (gridId > 0) {
            this.getGrid(gridId);
        }
    }

    getGrid(id) {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/solution/" + id
        ).then(data => {
            var processed = GridHelper.processData(data.cells, data.height, data.width, this.state.active_row, this.state.active_col);
            var cells = processed.cells;
            this.strips = processed.strips;
            cells = GridHelper.setLabels(cells, this.strips);
            this.setState({cells: cells, height: parseInt(data.height), width: parseInt(data.width), gridId: id, gridName: data.name});
        });
    }

    newGrid(h, w) {
        var c = GridHelper.getCellArray(h, w);
        var processed = GridHelper.processData(c, h, w, this.state.active_row, this.state.active_col);
        var cells = processed.cells;
        this.strips = processed.strips;
        this.setState({cells: cells, height: h, width: w, active_row: h-1, active_col: w-1, gridId: 0});
    }

    saveGame(name, asCopy) {
        if (!GridHelper.validGrid(this.state.cells, this.state.height, this.state.width)) {
            console.log('invalid for saving');
            console.log(GridHelper.message);
        }

        var cells = JSON.stringify(this.state.cells);
        name = name || null;
        return $.post(
            "http://kak.uro/app_dev.php/api/save-design",
            {
                grid_id: this.state.gridId,
                height: this.state.height,
                width: this.state.width,
                name: name,
                cells: cells,
                asCopy: ~~asCopy,
            },
            function(resp) {
                this.setState({gridName: resp.name, gridId: parseInt(resp.id)});
            }.bind(this),
            'json'
        );
    }

    getGames() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/games"
        ).then(data => {
            this.setState({grids: data.games});
        });
    }

    handleKey(e) {
        var keyCode = e.keyCode;
        if (keyCode === 38) { // up
            this.moveActive(-1,0);
        }
        if (keyCode === 40) { // down
            this.moveActive(1,0);
        }
        if (keyCode === 37) { // right
            this.moveActive(0,-1);
        }
        if (keyCode === 39) { //left
            this.moveActive(0,1);
        }
        if (keyCode === 88) { // x
            this.setActiveCellVal('x');
        }
        if (keyCode === 89) { // y
            this.setActiveCellVal('y');
        }
        if (keyCode === 82) { // r
            this.removeRow();
        }
        if (keyCode === 67) { // c
            this.removeCol();
        }
        if (keyCode === 65) { // a
            this.addRow();
        }
        if (keyCode === 66) { // b
            this.addCol();
        }
        if (keyCode > 48 && keyCode <= 57) { // 1-9
            this.setActiveCellVal(keyCode - 48);
        }
    }

    setActiveCellVal(val) {
        var idx = this.state.active_row * this.state.width + this.state.active_col;
        var cells = this.state.cells;

        if (!GridHelper.valAllowed(val, idx, cells, this.state.height, this.state.width)) {
            return;
        }

        switch(val) {
            case 'x':
                if (cells[idx].col == 0 || cells[idx].row == 0) {
                    return;
                }

                cells[idx].is_data = !cells[idx].is_data;
                cells[idx].choices = [];
                this.strips = GridHelper.allStripsLite(cells, this.state.height, this.state.width);
                break;
            case 'y':
                cells[idx].choices = [1,2,3,4,5,6,7,8,9];
                break;
            default:
                cells[idx].choices = [val];
        }

        // adjust other cells based on this action:
        // adjust labels
        cells = GridHelper.setLabels(cells, this.strips);

        // adjust possible values
        // cells = getChoices(cells).then(resp => {
        //     console.log('hi');
        //     console.log(resp.cells[9].choices);
        //     this.setState({cells: resp.cells});
        // });

        // cells = this.reduce(idx, cells, this.state.height, this.state.width);
        $.post(
            "http://kak.uro/app_dev.php/api/design/choices",
            {
                height: this.state.height,
                width: this.state.width,
                cells: JSON.stringify(cells),
            },
            function(resp) {
                cells = resp;
            },
            'json'
        ).then(cells => {
            this.setState({cells: cells});
        });

        // GridHelper.checkSwap(idx, cells, this.state.height, this.state.width);

        // this.setState({cells: cells});
    }

    removeRow() {
        var active_row = this.state.active_row;
        if (!active_row) {
            return;
        }
        var cells = this.state.cells;
        var height = this.state.height;
        var width = this.state.width;
        cells = GridHelper.removeRow(active_row, cells, height, width);
        this.strips = GridHelper.allStripsLite(cells, this.state.height - 1, this.state.width);
        cells = GridHelper.setLabels(cells, this.strips);
        this.setState({cells: cells, height: height - 1});
    }

    removeCol() {
        var active_col = this.state.active_col;
        if (!active_col) {
            return;
        }
        var cells = this.state.cells;
        var height = this.state.height;
        var width = this.state.width;
        cells = GridHelper.removeCol(active_col, cells, height, width);
        this.strips = GridHelper.allStripsLite(cells, this.state.height, this.state.width - 1);
        cells = GridHelper.setLabels(cells, this.strips);
        this.setState({cells: cells, width: width - 1});
    }

    addRow() {
        var cells = this.state.cells;
        var height = this.state.height;
        var width = this.state.width;
        var active_row = this.state.active_row;
        cells = GridHelper.insertRow(active_row, cells, height, width);
        this.strips = GridHelper.allStripsLite(cells, this.state.height + 1, this.state.width);
        cells = GridHelper.setLabels(cells, this.strips);
        // console.log(cells);
        this.setState({cells: cells, height: height + 1});
    }

    addCol() {
        var cells = this.state.cells;
        var height = this.state.height;
        var width = this.state.width;
        var active_col = this.state.active_col;
        cells = GridHelper.insertCol(active_col, cells, height, width);
        this.strips = GridHelper.allStripsLite(cells, this.state.height, this.state.width + 1);
        cells = GridHelper.setLabels(cells, this.strips);
        // console.log(cells);
        this.setState({cells: cells, width: width + 1});
    }

    reduce(idx, cells, h, w) {
        return GridHelper.reduce(idx, cells, h, w);
    }

    setActive(row, col) {
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        cells.forEach((v,k) => {
            cells[k].active=false;
        });
        cells[idx].active = true;
        this.setState({cells: cells, active_row: row, active_col: col});
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

    checkSolution() {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/check-uniqueness",
            {
                cells: cells,
                height: this.state.height,
                width: this.state.width,
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        ).then(data => {
            if (data.hasError) {
                alert('error');
                return;
            }
            if (!data.hasUniqueSolution) {
                alert('solution is not unique');
                // cells = GridHelper.setLabels(data.grid, this.strips);
                // this.setState({cells: cells});
            } else {
                alert('solution is unique');
            }
        });
    }

    render() {
        var classes = "kakuro-grid col-md-8";
        var cells = this.state.cells.map(function(cell, index) {
            cell.active = cell.row == this.state.active_row && cell.col == this.state.active_col;
            cell.semiactive = cell.row == this.state.active_row || cell.col == this.state.active_col;
            return <Cell 
                    cell={cell}
                    solved={false}
                    key={index}
                    onClick={() => this.setActive(cell.row, cell.col)}
                    onChange={this.handleChangedCell}
                   />;
        }, this);

        return (
            <div>
                <div className="kakuro-grid col-md-8" tabIndex="0" onKeyDown={this.handleKey}>
                   {cells}
                </div>
                <div className="col-md-4">
                    <KakuroControls
                        savedGameName={this.state.gridName}
                        height={this.state.height}
                        width={this.state.width}
                        selectedGrid={this.state.gridId}
                        save={this.saveGame}
                        grids={this.state.grids}
                        getGrid={this.getGrid}
                        newGrid={this.newGrid}
                        createMode={true}
                        checkSolution={this.checkSolution}
                    />
                </div>
            </div>
            );
    }
}

ReactDOM.render(<GridDesigner />, document.getElementById("content"));
