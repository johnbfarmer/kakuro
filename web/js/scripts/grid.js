var Grid = React.createClass({
    getInitialState: function() {
        return { cells: [], height: 0, width: 0, active_row: 1, active_col: 2 };
    },
    componentDidMount: function() {
        this.getGrid();
    },
    getGrid: function() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + this.props.filename
        ).then(data => {
            cells = this.processNewData(data.cells, data.height, data.width);
            this.setState({ cells: cells, height: data.height, width: data.width });
        });
    },
    processNewData: function(cells, height, width) {
        cells.forEach((cell, idx) => {
            cell.col = idx % width;
            cell.row = Math.floor(idx / width);
            cell.active = cell.row === this.state.active_row && cell.col === this.state.active_col;
            cells[idx] = cell;
        });
        return cells;
    },
    setActive: function(row, col) {
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        cells[idx].active = true;
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
        console.log(this.state.active_row, this.state.active_col);
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
        console.log('ch cell', row, col, val);
        this.setState({cells: cells});
    },
    render: function() {
        var cells = this.state.cells.map(function(cell, index) {
            return <Cell cell={cell} key={index} moveActive={this.moveActive} onClick={() => this.setActive(cell.row, cell.col)} onChange={this.handleChangedCell} />;
        }, this);
        return (
            <div className="kakuro-grid">
               {cells}
            </div>
        );
    }
});

var Cell = React.createClass({
    getInitialState: function() {
        var cell = this.props.cell;
        var editable = cell.is_data;
        var display = cell.choices.join('');
        if (!editable) {
            var leftText = cell.display[0] ? cell.display[0].toString() : "";
            var rightText = cell.display[1] ? cell.display[1].toString() : "";
            if (leftText.length > 0 || rightText.length > 0) {
                display = leftText + "\\" + rightText;
            }
        }
        return { 
            display: display, 
            choices: cell.choices,
            editable: editable, 
            active: cell.active, 
            row: cell.row,
            col: cell.col,
            remove: []
        };
    },
    componentDidUpdate: function() {
        // console.log(this.state.row, this.state.col, ' updated');
        this.state.cell = this.props.cell;
        this.state.active = this.state.cell.active;
        this.state.choices = this.props.cell.choices;
        this.state.remove = [];
        if (this.props.editable) {
            this.state.display = this.state.choices.join('');
        }
        if (this.state.active) {
            if (this.choiceInput) {
                this.choiceInput.focus();
            }
        }
    },
    getClasses: function() {
        var classes = "kakuro-cell";
        if (!this.state.editable) {
            classes = classes + " blnk";
        }
        if (this.state.active) {
            classes = classes + " red";
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
    handleKeyDown: function(event) {
        var keyCode = event.keyCode;
        if (keyCode === 38) { // up
            this.props.moveActive(-1,0);
        }
        if (keyCode === 40) {
            this.props.moveActive(1,0);
        }
        if (keyCode === 37) {
            this.props.moveActive(0,-1);
        }
        if (keyCode === 39) {
            this.props.moveActive(0,1);
        }

        var key = parseInt(event.key);
        var arr_pos = this.state.choices.indexOf(key);
        if (arr_pos > -1) {
            this.state.remove.push(key);
        } else {
            arr_pos = this.state.remove.indexOf(key);
            if (arr_pos > -1) {
                this.state.remove.splice(arr_pos, 1);
            }
        }
    },
    handleChange: function(event) {
        var str = event.target.value;
        var arr = str.split('');
        var choices = [];
        arr.forEach((v) => {
            var val = parseInt(v);
            if ([1,2,3,4,5,6,7,8,9].indexOf(val) > -1 && choices.indexOf(val) < 0 && this.state.remove.indexOf(val) < 0) {
                choices.push(val);
            }
        });
        choices.sort();
        this.state.choices = choices;
        // console.log(choices);
        this.props.onChange(this.state.row, this.state.col, this.state.choices);
    },
    render: function() {
        if (this.props.cell.active) {
            return (
                <div className={this.getClasses()}>
                    <input type="text" onKeyDown={this.handleKeyDown} value={this.props.cell.choices.join('')} onChange={this.handleChange} ref={(input) => { this.choiceInput = input; }} />
                </div>
            );
        }
        return (
            <div className={this.getClasses()} onClick={() => this.setActive()}>
                {this.state.display}
            </div>
        );
    }
});

ReactDOM.render(<Grid filename={filename}/>, document.getElementById("content"));
