var Grid = React.createClass({
    getInitialState: function() {
        return { cells: [], height: 0, width: 0, active_row: -1, active_col: -1, saved_states: [] };
    },
    componentDidMount: function() {
        this.getGrid();
    },
    getGrid: function() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + this.props.grid_name
        ).then(data => {
            cells = this.processNewData(data.cells, data.height, data.width);
            this.setState({ cells: cells, height: data.height, width: data.width });
            this.saveState();
        });
    },
    saveState: function() {
        var cells = $.extend(true, [], this.state.cells);
        this.state.saved_states.push(cells);
    },
    restoreSavedState: function() {
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
    },
    saveChoices: function() {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/save-choices",
            {
                grid_name: this.props.grid_name,
                cells: cells
            },
            function(resp) {
                if (resp.error) {
                    alert(resp.message);
                }
            },
            'json'
        );
    },
    reduce: function(advanced) {
        var cells = JSON.stringify(this.state.cells);
        return $.post(
            "http://kak.uro/app_dev.php/api/get-choices",
            {
                grid_name: this.props.grid_name,
                cells: cells,
                advanced: ~~advanced
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
    },
    simpleReduce: function() {
        this.reduce(false);
    },
    advancedReduce: function() {
        this.reduce(true);
    },
    clearChoices: function() {
        var cells = this.state.cells;
        var idx = this.state.active_row * this.state.width + this.state.active_col;
        cells[idx].choices = [];

        this.setState({ cells: cells });
    },
    clearAllChoices: function() {
        var cells = this.state.cells;
        cells.forEach((cell, idx) => {
            cell.choices = [];
        });

        this.setState({ cells: cells });
    },
    processNewData: function(cells, height, width) {
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
    },
    setActive: function(row, col) {
        var fidx = this.state.active_row * this.state.width + this.state.active_col;
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        if (fidx >= 0) {
            cells[fidx].active = false;
        }
        cells[idx].active = true;
        this.saveState();
        this.setState({cells: cells, active_row: row, active_col: col});
    },
    moveActive: function(v,h, row, col) {
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
    },
    handleChangedCell: function(row, col, val) {
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        cells[idx].choices = val;
        this.setState({cells: cells});
    },
    handleKeyDown: function(event) {
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
            this.setState({cells: cells});
        } else {
            var keyCode = event.keyCode;
            this.handleKey(keyCode);
        }
    },
    handleKey: function(keyCode) {
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
        if (keyCode === 82) { // r
            this.simpleReduce();
        }
        if (keyCode === 65) { // a
            this.advancedReduce();
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
    },
    render: function() {
        var cells = this.state.cells.map(function(cell, index) {
            return <Cell cell={cell} key={index} handleKey={this.handleKey} onClick={() => this.setActive(cell.row, cell.col)} onChange={this.handleChangedCell} />;
        }, this);
        return (
            <span className="kakuro-grid" tabIndex="0" onKeyDown={this.handleKeyDown}>
               {cells}
            </span>
        );
    }
});

var Cell = React.createClass({
    getInitialState: function() {
        var cell = this.props.cell;
        var editable = cell.is_data;
        var display = cell.choices.join('');
        var label_v = '';
        var label_h = '';
        var sum_box = false;
        if (!editable) {
            label_v = cell.display[0] ? cell.display[0].toString() : '';
            label_h = cell.display[1] ? cell.display[1].toString() : '';
            if (label_h.length > 0 || label_v.length > 0) {
                sum_box = true;
            }
        }
        return { 
            display: display,
            label_v: label_v,
            label_h: label_h,
            sum_box: sum_box,
            choices: cell.choices,
            editable: editable, 
            active: cell.active, 
            row: cell.row,
            col: cell.col,
            remove: []
        };
    },
    componentDidUpdate: function() {
        var cell = this.props.cell;
        this.state.active = cell.active;
        this.state.choices = cell.choices;
        this.state.remove = [];
        if (this.state.editable) {
            this.state.display = cell.choices.join('');
        }
    },
    getClasses: function() {
        var classes = "kakuro-cell";
        if (!this.state.editable) {
            classes = classes + " blnk";
        }
        if (this.state.sum_box) {
            classes = classes + " sum-box";
        }
        if (this.props.cell.active) {
            classes = classes + " actv";
        }
        if (this.state.col === 0) {
            classes = classes + " clr";
        }
        return classes;
    },
    setActive: function() {
        if (this.state.editable) {
            this.props.onClick();
        }
    },
    render: function() {
        if (this.state.editable) {
            return (
                <div className={this.getClasses()} onClick={() => this.setActive()}>
                    <span className='choice-box'>{this.props.cell.choices.join('')}</span>
                </div>
            );
        }
        return (
            <div className={this.getClasses()}>
                <div className='label-v'>{this.state.label_v}</div><div className='label-h'>{this.state.label_h}</div>
            </div>
        );
    }
});

ReactDOM.render(<Grid grid_name={grid_name}/>, document.getElementById("content"));
