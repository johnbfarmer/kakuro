var Grid = React.createClass({
    getInitialState: function() {
        return { cells: [], height: 0, width: 0, active_row: null, active_col: null };
    },
    componentDidMount: function() {
        // console.log(this.getDOMNode());
        this.getGrid();
    },
    getGrid: function() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/medium1.kak"
        ).then(data => {
            // console.log(data);
            this.setState({ cells: data.cells, height: data.height, width: data.width });
            // console.log(this.state.cells);
        });
    },
    render: function() {
        var cells = this.state.cells.map(function(cell, index) {
            var col = index % this.state.width;
            var row = Math.floor(index / this.state.width);
            var new_row = col === 0;
            return <Cell cell={cell} key={index} row={row} col={col} new_row={new_row} />;
        }, this);
        return (
            <div>
               {cells}
            </div>
        );
    }
});

var Cell = React.createClass({
    getInitialState: function() {
        var editable = this.props.cell == null;
        if (!editable) {
            var leftText = this.props.cell[0] ? this.props.cell[0] : "";
            var rightText = this.props.cell[1] ? this.props.cell[1] : "";
        }
        var txt = editable ? "" : leftText + "\\" + rightText;
        return { 
            cell: txt, 
            editable: editable, 
            active: this.props.active, 
            row: this.props.row,
            col: this.props.col,
            new_row: this.props.new_row,
        };
    },
    getClasses: function() {
        var classes = "kakuro-cell";
        if (!this.state.editable) {
            classes = classes + " blnk";
        }
        if (this.state.active) {
            classes = classes + " red";
        }
        if (this.state.new_row) {
            classes = classes + " clr";
        }
        return classes;
    },
    setActive: function() {
        this.setState({ active: true });
    },
    render: function() {
        return (
            <div className={this.getClasses()} onClick={this.setActive}>
                {this.state.cell}
            </div>
        );
    }
});

React.render(<Grid />, document.getElementById("content"));
// ReactDOM.render(<Grid />, document.getElementById('content'));
