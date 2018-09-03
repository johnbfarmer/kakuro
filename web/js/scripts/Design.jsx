import React from 'react';
import ReactDOM from 'react-dom';
import Cell from './Cell.jsx';
import KakuroControls from './KakuroControls.jsx';
import {adjustLabels, getCellArray} from './f.js';

export default class GridDesigner extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            grids: [],
            gridId: 0,
            cells: [],
            height: 0,
            width: 0,
            active_row: -1,
            active_col: -1,
        };

        this.getGrid = this.getGrid.bind(this);
        this.saveChoices = this.saveChoices.bind(this);
        this.getGames = this.getGames.bind(this);
        this.processNewData = this.processNewData.bind(this);
        this.setActive = this.setActive.bind(this);
        this.moveActive = this.moveActive.bind(this);
        this.handleKey = this.handleKey.bind(this);
        this.newGrid = this.newGrid.bind(this);
        this.setActiveCellVal = this.setActiveCellVal.bind(this);
        
    }

    componentDidMount() {
        this.getGames();
    }

    getGrid(id) {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + id
        ).then(data => {
            console.log('gg ', data);
            var cells = this.processNewData(data.cells, data.height, data.width);
            this.setState({cells: cells, height: data.height, width: data.width, gridId: id});
        });
    }

    newGrid(h, w) {
        var c = getCellArray(h, w);
        console.log(c);
        var cells = this.processNewData(c, h, w);
        this.setState({cells: cells, height: h, width: w, active_row: h-1, active_col: w-1, gridId: 0});
    }

    saveChoices() {
        console.log('sc');
    }

    getGames() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/games"
        ).then(data => {
            this.setState({grids: data.games});
        });
    }

    processNewData(cells, height, width) {
        cells.forEach((cell, idx) => {
            cell.col = idx % width;
            cell.row = Math.floor(idx / width);
            if (!('display' in cell)) {
                cell.display = [0,0];
            }
            if (cell.row == 0 || cell.col == 0) {
                cell.is_data = false;
                cell.display = cell.display || [0,0];
            } else {
                cell.is_data = true;
            }
            cell.active = cell.row === this.state.active_row && cell.col === this.state.active_col;
            cells[idx] = cell;
        });
        return cells;
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
        if (keyCode > 48 && keyCode <= 57) { // 1-9
            this.setActiveCellVal(keyCode - 48);
        }
    }

    setActiveCellVal(val) {
        var idx = this.state.active_row * this.state.width + this.state.active_col;
        var cells = this.state.cells;
        switch(val) {
            case 'x':
                cells[idx].is_data = !cells[idx].is_data;
                break;
            default:
                cells[idx].choices = [val];
        }

        // adjust other cells based on this action:
        // adjust labels
        cells = adjustLabels(idx, cells, this.state.height, this.state.width);

        // adjust possible values
        // TBI

        this.setState({cells: cells});
    }

    setActive(row, col) {
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        cells.forEach((v,k) => {
            cells[k].active=false;
        });
        cells[idx].active = true;
        // cells[idx].choices = [1,2,3,4,5,6,7,8,9];Down
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

        if (!this.state.cells[active_row * this.state.width + active_col].is_editable) {
            this.moveActive(v, h, active_row, active_col);
        } else {
            this.setActive(active_row, active_col);
        }
    }

    render() {
        var classes = "kakuro-grid col-md-8";
        var cells = this.state.cells.map(function(cell, index) {
            cell.active = cell.row == this.state.active_row && cell.col == this.state.active_col;
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
                        savedGameName=''
                        selectedGrid={this.state.gridId}
                        save={this.saveChoices}
                        grids={this.state.grids}
                        getGrid={this.getGrid}
                        newGrid={this.newGrid}
                        createMode={true}
                    />
                </div>
            </div>
            );
    }
}

ReactDOM.render(<GridDesigner />, document.getElementById("content"));